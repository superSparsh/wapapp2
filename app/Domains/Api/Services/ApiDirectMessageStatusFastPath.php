<?php

declare(strict_types=1);

namespace App\Domains\Api\Services;

use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Models\MessageExternalIndex;
use Illuminate\Support\Facades\Log;

/**
 * Prefer immediate (sync) status processing for partner API direct messages so
 * delivery/read updates do not wait on the OCI status queue.
 */
class ApiDirectMessageStatusFastPath
{
    public function __construct(
        private readonly AlibabaWebhookParser $parser,
        private readonly WhatsappLineRegistryService $registryService,
    ) {}

    /**
     * @param  array<string, mixed>  $statusItem  Normalized Alibaba status item
     */
    public function shouldSyncStatusWebhook(array $statusItem): bool
    {
        $externalId = $this->parser->messageIdempotencyKey($statusItem);
        if ($externalId === null) {
            return false;
        }

        return $this->isApiOutboundExternalId($externalId);
    }

    public function isApiOutboundMessage(Message $message): bool
    {
        $meta = is_array($message->metadata) ? $message->metadata : [];

        if ((bool) ($meta['api_send'] ?? false)) {
            return true;
        }

        return strtolower((string) ($meta['wallet_source'] ?? '')) === 'api';
    }

    /**
     * Process any stuck inbound status rows for this message before returning API status.
     */
    public function refreshPendingStatusEventsForMessage(Message $message): void
    {
        if (! $this->isApiOutboundMessage($message)) {
            return;
        }

        $externalId = trim((string) ($message->external_message_id ?? ''));
        if ($externalId === '') {
            return;
        }

        $events = InboundWebhookEvent::query()
            ->where('event_type', InboundWebhookEventType::Status)
            ->whereIn('status', [
                InboundWebhookStatus::Received->value,
                InboundWebhookStatus::Failed->value,
            ])
            ->where('idempotency_key', 'like', $externalId.'%')
            ->orderBy('id')
            ->limit(20)
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $callerTenant = tenancy()->initialized ? tenant() : null;

        foreach ($events as $event) {
            try {
                $event->forceFill([
                    'status' => InboundWebhookStatus::Received,
                    'error_message' => null,
                ])->save();

                dispatch_sync(new ProcessInboundWebhookJob((int) $event->id));
            } catch (\Throwable $exception) {
                Log::warning('API status fast-path refresh failed', [
                    'event_id' => $event->id,
                    'external_message_id' => $externalId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($callerTenant !== null) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            tenancy()->initialize($callerTenant);
        }
    }

    private function isApiOutboundExternalId(string $externalMessageId): bool
    {
        $externalMessageId = trim($externalMessageId);
        if ($externalMessageId === '') {
            return false;
        }

        $index = MessageExternalIndex::query()
            ->where('external_message_id', $externalMessageId)
            ->first();

        $tenant = $index !== null
            ? $this->registryService->resolveTenantByExternalMessageId($externalMessageId)
            : null;

        if ($tenant === null) {
            return false;
        }

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        try {
            if (! $wasInitialized || (string) optional($previous)->getTenantKey() !== (string) $tenant->getTenantKey()) {
                if ($wasInitialized) {
                    tenancy()->end();
                }
                tenancy()->initialize($tenant);
            }

            $message = null;
            if ($index?->message_id) {
                $message = Message::query()->find((int) $index->message_id);
            }

            $message ??= Message::query()
                ->where('external_message_id', $externalMessageId)
                ->first();

            return $message instanceof Message && $this->isApiOutboundMessage($message);
        } catch (\Throwable $exception) {
            Log::debug('API status fast-path lookup failed', [
                'external_message_id' => $externalMessageId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($previous !== null) {
                tenancy()->initialize($previous);
            }
        }
    }
}
