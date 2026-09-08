<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Jobs;

use App\Domains\Webhooks\Services\WebhookDeliveryService;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchOutboundWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $eventType,
        public readonly array $payload,
    ) {
        $this->backoff = config('webhooks.backoff', [10, 60, 300]);
        $this->onQueue(config('webhooks.queue', 'default'));
    }

    public function handle(WebhookDeliveryService $service): void
    {
        $subscription = WebhookSubscription::query()->find($this->subscriptionId);

        if (! $subscription) {
            Log::warning('Outbound webhook job: subscription not found', [
                'subscription_id' => $this->subscriptionId,
                'event_type' => $this->eventType,
            ]);

            return;
        }

        $service->dispatch($subscription, $this->eventType, $this->payload);
    }

    /**
     * Dead letter: called after all retries exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Outbound webhook permanently failed (dead letter)', [
            'subscription_id' => $this->subscriptionId,
            'event_type' => $this->eventType,
            'error' => $exception?->getMessage(),
        ]);
    }
}
