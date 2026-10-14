<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Billing\Services\SubscriptionService;
use App\Models\Plan;
use App\Models\User;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendPlanExpirationAlertsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'alerts:plan-expiration {--tenants=* : Tenant IDs to process}';

    protected $description = 'Send plan expiration email/WhatsApp reminders (30/7/3/1 days).';

    public function handle(AlertDispatcher $dispatcher, SubscriptionService $subscriptions): int
    {
        $reminderDays = array_map('intval', (array) config('operational-alerts.plan_expiration.reminder_days', [30, 7, 3, 1]));
        $sent = 0;

        $this->foreachTenant(function () use ($dispatcher, $subscriptions, $reminderDays, &$sent): void {
            $summary = $subscriptions->subscriptionSummary();
            $endsAt = $summary['expires_at'] ?? null;
            if (! $endsAt instanceof Carbon) {
                return;
            }

            $daysLeft = (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false);
            if ($daysLeft < 0 || ! in_array($daysLeft, $reminderDays, true)) {
                return;
            }

            $plan = $summary['plan']
                ?? (object) [
                    'name' => $summary['plan_name'] ?? 'Plan',
                    'price' => $summary['subscription']?->amount,
                    'currency' => $summary['subscription']?->currency,
                ];

            if (! $plan instanceof Plan && ($summary['subscription']?->plan_id ?? null)) {
                $plan = tenancy()->central(
                    fn () => Plan::query()->find($summary['subscription']->plan_id)
                ) ?? $plan;
            }

            $user = User::query()->orderBy('id')->first();
            $subscription = $summary['subscription'] ?? $dispatcher->ensureActiveSubscriptionForPlanAlerts();

            $dispatcher->planExpiration($subscription, $daysLeft, $plan, $user, $endsAt);
            $sent++;
        });

        $this->info("Plan expiration alerts processed ({$sent} tenant(s) notified).");

        return self::SUCCESS;
    }
}
