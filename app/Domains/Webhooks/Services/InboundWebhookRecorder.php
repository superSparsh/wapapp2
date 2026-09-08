<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Database\QueryException;
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

            if ($event->status !== InboundWebhookStatus::Processed) {
                $event->forceFill(['status' => InboundWebhookStatus::Duplicate])->save();
            }

            return $event;
        }

        ProcessInboundWebhookJob::dispatch($event->id)
            ->onQueue((string) config('webhooks.queue', 'default'));

        return $event;
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
