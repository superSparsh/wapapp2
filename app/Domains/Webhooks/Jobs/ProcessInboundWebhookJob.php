<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Jobs;

use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Domains\Webhooks\Handlers\InboundMessageHandler;
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
    ): void {
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
                InboundWebhookEventType::Status => $statusHandler->handle($event),
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
}
