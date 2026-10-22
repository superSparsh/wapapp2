<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Billing\Services\SubscriptionService;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendPlanExpirationAlertsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'alerts:plan-expiration {--tenants=* : Tenant IDs to process}';

    protected $description = 'Send plan expiration email/WhatsApp reminders from tenants.settings.valid_until (30/7/3/1 days).';

    public function handle(AlertDispatcher $dispatcher, SubscriptionService $subscriptions): int
    {
        $reminderDays = array_map('intval', (array) config('operational-alerts.plan_expiration.reminder_days', [30, 7, 3, 1]));
        $sent = 0;

        $this->foreachTenant(function (Tenant $tenant) use ($dispatcher, $subscriptions, $reminderDays, &$sent): void {
            $endsAt = tenancy()->central(function () use ($tenant): ?Carbon {
                $fresh = Tenant::query()->find($tenant->id);

                return $fresh instanceof Tenant ? $this->validUntil($fresh) : null;
            });
            if (! $endsAt instanceof Carbon) {
                return;
            }

            $daysLeft = (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false);
            if ($daysLeft < 0 || ! in_array($daysLeft, $reminderDays, true)) {
                return;
            }

            $summary = $subscriptions->subscriptionSummary();

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

    /**
     * Admin expiry. Central tenants.settings.valid_until only.
     */
    private function validUntil(Tenant $tenant): ?Carbon
    {
        $settings = $tenant->settings;
        if (is_string($settings) && $settings !== '') {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($settings)) {
            $settings = [];
        }

        $raw = $settings['valid_until'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
