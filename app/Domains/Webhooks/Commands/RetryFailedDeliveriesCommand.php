<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\Webhooks\Jobs\DispatchOutboundWebhookJob;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookSubscriptionStatus;
use App\Models\WebhookDelivery;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RetryFailedDeliveriesCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'webhooks:retry-failed
        {--limit=50 : Maximum deliveries to retry per tenant}
        {--tenants=* : Tenant IDs to process}';

    protected $description = 'Dispatch retry jobs for failed webhook deliveries past their next_retry_at time';

    public function handle(): int
    {
        if ($this->skipForMaintenance('outbound_webhooks', 'Webhooks:')) {
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dispatched = 0;
        $failedTenants = 0;

        $this->foreachTenant(function () use ($limit, &$dispatched, &$failedTenants): void {
            try {
                $dispatched += $this->retryForCurrentTenant($limit);
            } catch (Throwable $e) {
                $failedTenants++;
                $this->error('Webhook retry failed for tenant: '.$e->getMessage());
                report($e);
            }
        });

        $this->info("Dispatched {$dispatched} retry job(s).");

        return $failedTenants > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function retryForCurrentTenant(int $limit): int
    {
        if (! Schema::hasTable('webhook_deliveries')) {
            $this->warn('Skipping tenant: webhook_deliveries table missing (run tenant migrations).');

            return 0;
        }

        $deliveries = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Failed)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->whereHas('subscription', fn ($q) => $q->where('status', WebhookSubscriptionStatus::Active))
            ->limit($limit)
            ->get();

        if ($deliveries->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($deliveries as $delivery) {
            DispatchOutboundWebhookJob::dispatch(
                subscriptionId: (int) $delivery->webhook_subscription_id,
                eventType: $delivery->event_type,
                payload: is_array($delivery->payload) ? $delivery->payload : [],
            )->onQueue(config('webhooks.queue', 'default'));

            $count++;
        }

        return $count;
    }
}
