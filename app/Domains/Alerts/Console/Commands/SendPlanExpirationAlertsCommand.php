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
use Illuminate\Support\Facades\Log;

class SendPlanExpirationAlertsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'alerts:plan-expiration {--tenants=* : Tenant IDs to process}';

    protected $description = 'Send plan expiration email/WhatsApp reminders from tenants.settings.valid_until (30/7/3/1 days, with catch-up).';

    public function handle(AlertDispatcher $dispatcher, SubscriptionService $subscriptions): int
    {
        $reminderDays = array_map('intval', (array) config('operational-alerts.plan_expiration.reminder_days', [30, 7, 3, 1]));
        $maxReminder = $reminderDays !== [] ? max($reminderDays) : 30;
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $this->foreachTenant(function (Tenant $tenant) use ($dispatcher, $subscriptions, $maxReminder, &$sent, &$skipped, &$failed): void {
            try {
                $endsAt = tenancy()->central(function () use ($tenant): ?Carbon {
                    $fresh = Tenant::query()->find($tenant->id);

                    return $fresh instanceof Tenant ? $this->validUntil($fresh) : null;
                });
                if (! $endsAt instanceof Carbon) {
                    $skipped++;

                    return;
                }

                $daysLeft = $this->calendarDaysLeft($endsAt);
                if ($daysLeft < 0 || $daysLeft > $maxReminder) {
                    $skipped++;

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

                $didSend = $dispatcher->planExpiration($subscription, $daysLeft, $plan, $user, $endsAt);
                if ($didSend) {
                    $sent++;
                    $this->line(sprintf(
                        'Sent plan expiration alert: tenant=%s days_left=%d ends=%s owner=%s',
                        $tenant->id,
                        $daysLeft,
                        $endsAt->toDateString(),
                        $user?->email ?? 'none',
                    ));
                    Log::info('Plan expiration alert sent', [
                        'tenant_id' => $tenant->id,
                        'days_left' => $daysLeft,
                        'ends_at' => $endsAt->toDateString(),
                        'owner_email' => $user?->email,
                    ]);
                } else {
                    // In window, but all due thresholds already marked sent (dedupe) — no mail.
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Plan expiration alert failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                report($e);
            }
        });

        $this->info("Plan expiration alerts processed (notified={$sent}, skipped={$skipped}, failed={$failed}).");

        return self::SUCCESS;
    }

    /**
     * Whole calendar days from today (app timezone) until valid_until.
     */
    private function calendarDaysLeft(Carbon $endsAt): int
    {
        $today = now()->startOfDay();
        $end = $endsAt->copy()->startOfDay();

        return (int) round($today->diffInDays($end, false));
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
