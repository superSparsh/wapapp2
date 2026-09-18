<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Jobs;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Domains\Webhooks\Handlers\InboundMessageHandler;
use App\Domains\Webhooks\Handlers\TemplateAuditWebhookHandler;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessInboundWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $eventId,
    ) {}

    public function handle(
        InboundMessageHandler $messageHandler,
        DeliveryStatusHandler $statusHandler,
        TemplateAuditWebhookHandler $templateAuditHandler,
        AlibabaWebhookParser $parser,
        MaintenanceModeService $maintenance,
    ): void {
        if (! $maintenance->moduleEnabled('inbound_webhooks')) {
            return;
        }

        $event = InboundWebhookEvent::query()->find($this->eventId);

        if ($event === null) {
            return;
        }

        if (in_array($event->status, [InboundWebhookStatus::Processed, InboundWebhookStatus::Duplicate], true)) {
            return;
        }

        $event->forceFill([
            'status' => InboundWebhookStatus::Processing,
            'retry_count' => $event->retry_count + 1,
        ])->save();

        try {
            match ($event->event_type) {
                InboundWebhookEventType::Message => $messageHandler->handle($event),
                InboundWebhookEventType::Status => $this->handleStatus(
                    $event,
                    $statusHandler,
                    $templateAuditHandler,
                    $parser,
                ),
            };

            $event->forceFill([
                'status' => InboundWebhookStatus::Processed,
                'processed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => InboundWebhookStatus::Failed,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function handleStatus(
        InboundWebhookEvent $event,
        DeliveryStatusHandler $statusHandler,
        TemplateAuditWebhookHandler $templateAuditHandler,
        AlibabaWebhookParser $parser,
    ): void {
        $item = $parser->firstItem($parser->parsePayload($event->payload));

        if ($item !== null && $templateAuditHandler->looksLikeTemplateAudit($item)) {
            $templateAuditHandler->handle($event);

            return;
        }

        $statusHandler->handle($event);
    }
}
