<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Models\Plan;
use App\Models\User;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class SendPlanExpirationAlertsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'alerts:plan-expiration {--tenants=* : Tenant IDs to process}';

    protected $description = 'Send plan expiration email/WhatsApp reminders (30/7/3/1 days).';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $reminderDays = array_map('intval', (array) config('operational-alerts.plan_expiration.reminder_days', [30, 7, 3, 1]));
        $sent = 0;

        $this->foreachTenant(function () use ($dispatcher, $reminderDays, &$sent): void {
            $subscription = $dispatcher->ensureActiveSubscriptionForPlanAlerts();
            if ($subscription === null || $subscription->ends_at === null) {
                return;
            }

            $daysLeft = (int) now()->startOfDay()->diffInDays($subscription->ends_at->copy()->startOfDay(), false);
            if ($daysLeft < 0 || ! in_array($daysLeft, $reminderDays, true)) {
                return;
            }

            $plan = Plan::query()->find($subscription->plan_id)
                ?? (object) ['name' => 'Plan', 'price' => $subscription->amount, 'currency' => $subscription->currency];
            $user = User::query()->orderBy('id')->first();

            $dispatcher->planExpiration($subscription, $daysLeft, $plan, $user);
            $sent++;
        });

        $this->info("Plan expiration alerts processed ({$sent} tenant(s) notified).");

        return self::SUCCESS;
    }
}
