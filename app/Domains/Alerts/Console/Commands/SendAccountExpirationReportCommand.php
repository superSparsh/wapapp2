<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Console\Commands;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendAccountExpirationReportCommand extends Command
{
    protected $signature = 'alerts:account-expiration-report {--within-days=45 : Include accounts ending within N days}';

    protected $description = 'Email admins a monthly account expiration report.';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $within = max(1, (int) $this->option('within-days'));
        $windowStart = now()->startOfDay();
        $windowEnd = now()->addDays($within)->endOfDay();
        $rows = [];

        foreach (Tenant::query()->with('plan:id,name')->cursor() as $tenant) {
            $endsAt = $this->resolveExpiresAt($tenant);
            if ($endsAt === null) {
                continue;
            }

            if ($endsAt->lt($windowStart) || $endsAt->gt($windowEnd)) {
                continue;
            }

            $rows[] = [
                'tenant' => $tenant->company_name ?: $tenant->name ?: (string) $tenant->id,
                'email' => (string) ($tenant->email ?? ''),
                'plan' => $tenant->plan?->name ?? '-',
                'ends_at' => $endsAt->format('d M Y'),
                'days_left' => (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($a['days_left'] <=> $b['days_left']));

        $dispatcher->accountExpirationReport($rows);
        $this->info('Account expiration report sent ('.count($rows).' row(s)).');

        return self::SUCCESS;
    }

    /**
     * Prefer central tenant settings.valid_until (admin / legacy source of truth),
     * then fall back to active subscription ends_at in the tenant DB.
     */
    private function resolveExpiresAt(Tenant $tenant): ?Carbon
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $raw = $settings['valid_until'] ?? null;
        if (filled($raw)) {
            try {
                return Carbon::parse((string) $raw)->endOfDay();
            } catch (\Throwable) {
                // fall through
            }
        }

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        try {
            tenancy()->initialize($tenant);

            $subscription = Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->whereNotNull('ends_at')
                ->orderByDesc('ends_at')
                ->first();

            return $subscription?->ends_at?->copy()->endOfDay();
        } catch (\Throwable) {
            return null;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($wasInitialized && $previous) {
                tenancy()->initialize($previous);
            }
        }
    }
}
