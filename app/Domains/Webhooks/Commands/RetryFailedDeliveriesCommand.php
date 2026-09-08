<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Commands;

use App\Domains\Webhooks\Jobs\DispatchOutboundWebhookJob;
use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

class RetryFailedDeliveriesCommand extends Command
{
    protected $signature = 'webhooks:retry-failed {--limit=50 : Maximum deliveries to retry per run}';

    protected $description = 'Dispatch retry jobs for failed webhook deliveries past their next_retry_at time';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $deliveries = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Failed)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->whereHas('subscription', fn ($q) => $q->where('status', 'active'))
            ->limit($limit)
            ->get();

        if ($deliveries->isEmpty()) {
            $this->info('No failed deliveries due for retry.');

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($deliveries as $delivery) {
            DispatchOutboundWebhookJob::dispatch(
                subscriptionId: (int) $delivery->webhook_subscription_id,
                eventType: $delivery->event_type,
                payload: $delivery->payload,
            )->onQueue(config('webhooks.queue', 'default'));

            $count++;
        }

        $this->info("Dispatched {$count} retry job(s).");

        return self::SUCCESS;
    }
}
