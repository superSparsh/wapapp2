<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use App\Support\OciWorkload;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InboundWebhookRecorder
{
    public function __construct(
        private readonly AlibabaWebhookParser $parser,
    ) {}

    public function record(
        InboundWebhookEventType $eventType,
        string $rawBody,
        array $headers = [],
    ): InboundWebhookEvent {
        $payload = $this->decodePayload($rawBody);
        $items = $this->parser->parsePayload($payload);
        $first = $this->parser->firstItem($items) ?? [];

        $idempotencyKey = $eventType === InboundWebhookEventType::Message
            ? $this->parser->messageIdempotencyKey($first)
            : $this->parser->statusIdempotencyKey($first);

        $idempotencyKey ??= 'hash:'.hash('sha256', $rawBody);

        try {
            $event = InboundWebhookEvent::query()->create([
                'event_type' => $eventType,
                'idempotency_key' => Str::limit($idempotencyKey, 191, ''),
                'payload' => is_array($payload) ? $payload : ['raw' => $rawBody],
                'headers' => $headers,
                'status' => InboundWebhookStatus::Received,
                'created_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            $event = InboundWebhookEvent::query()
                ->where('event_type', $eventType)
                ->where('idempotency_key', Str::limit($idempotencyKey, 191, ''))
                ->firstOrFail();

            // Already handled — Alibaba retry is a true duplicate.
            if ($event->status === InboundWebhookStatus::Processed) {
                return $event;
            }

            // Still pending/failed: re-queue the original row instead of marking "duplicate"
            // (which would permanently skip ProcessInboundWebhookJob).
            $this->dispatchProcessing($event, $eventType);

            return $event->refresh();
        }

        $this->dispatchProcessing($event, $eventType);

        return $event->refresh();
    }

    private function dispatchProcessing(InboundWebhookEvent $event, InboundWebhookEventType $eventType): void
    {
        if (in_array($event->status, [InboundWebhookStatus::Processed, InboundWebhookStatus::Processing], true)) {
            return;
        }

        $queue = OciWorkload::queueForInboundEvent($eventType);
        $skipSync = $eventType === InboundWebhookEventType::Status
            && OciWorkload::statusShouldSkipSync();

        if (! $skipSync) {
            try {
                dispatch_sync(new ProcessInboundWebhookJob($event->id));
            } catch (\Throwable $exception) {
                Log::warning('Inbound webhook sync processing failed; queued retry remains', [
                    'event_id' => $event->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $event->refresh();

        if (! in_array($event->status, [InboundWebhookStatus::Processed, InboundWebhookStatus::Duplicate], true)) {
            try {
                ProcessInboundWebhookJob::dispatch($event->id)->onQueue($queue);
            } catch (\Throwable $exception) {
                Log::warning('Inbound webhook queued retry failed', [
                    'event_id' => $event->id,
                    'queue' => $queue,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function decodePayload(string $rawBody): mixed
    {
        $decoded = json_decode($rawBody, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return ['raw' => $rawBody];
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'duplicate')
            || str_contains($message, 'unique');
    }
}
