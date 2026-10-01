<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Monthly account expiry report — mirrors legacy AccountExpirationReportService buckets.
 */
class AccountExpirationReportService
{
    /**
     * @return array{
     *     generated_at: Carbon,
     *     expired: list<array<string, mixed>>,
     *     expiring_within_30: list<array<string, mixed>>,
     *     expiring_30_90: list<array<string, mixed>>,
     * }
     */
    public function buildReport(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();

        $rows = $this->collectRows($asOf);

        $expired = $rows
            ->filter(fn (array $row): bool => $row['days_left'] <= 0)
            ->sortByDesc('ends_at_ts')
            ->values()
            ->map(fn (array $row): array => $this->mapRow($row, 'expired'))
            ->all();

        $within30 = $rows
            ->filter(fn (array $row): bool => $row['days_left'] >= 1 && $row['days_left'] <= 30)
            ->sortBy('ends_at_ts')
            ->values()
            ->map(fn (array $row): array => $this->mapRow($row, 'expiring'))
            ->all();

        $within90 = $rows
            ->filter(fn (array $row): bool => $row['days_left'] >= 31 && $row['days_left'] <= 90)
            ->sortBy('ends_at_ts')
            ->values()
            ->map(fn (array $row): array => $this->mapRow($row, 'expiring'))
            ->all();

        return [
            'generated_at' => $asOf,
            'expired' => $expired,
            'expiring_within_30' => $within30,
            'expiring_30_90' => $within90,
        ];
    }

    /**
     * @return Collection<int, array{tenant_id: string, name: string, email: string, plan_name: string, ends_at: Carbon, ends_at_ts: int, days_left: int}>
     */
    private function collectRows(Carbon $asOf): Collection
    {
        $out = collect();

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->with('plan:id,name')
            ->orderBy('id')
            ->cursor()
            ->each(function (Tenant $tenant) use ($asOf, $out): void {
                $endsAt = $this->resolveExpiresAt($tenant);
                if ($endsAt === null) {
                    return;
                }

                $daysLeft = (int) $asOf->copy()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false);
                // Legacy only includes ≤90 days ahead + all expired; skip farther future.
                if ($daysLeft > 90) {
                    return;
                }

                $out->push([
                    'tenant_id' => (string) $tenant->id,
                    'name' => trim((string) ($tenant->company_name ?: $tenant->name ?: $tenant->id)) ?: (string) $tenant->id,
                    'email' => trim((string) ($tenant->email ?? '')),
                    'plan_name' => trim((string) ($tenant->plan?->name ?? '')) ?: '—',
                    'ends_at' => $endsAt->copy(),
                    'ends_at_ts' => $endsAt->timestamp,
                    'days_left' => $daysLeft,
                ]);
            });

        return $out;
    }

    /**
     * Prefer central tenant settings.valid_until (admin / legacy migration source of truth),
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
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Expired])
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

    /**
     * @param  array{tenant_id: string, name: string, email: string, plan_name: string, ends_at: Carbon, ends_at_ts: int, days_left: int}  $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row, string $bucket): array
    {
        $daysLeft = $row['days_left'];

        if ($bucket === 'expired') {
            $daysLabel = $daysLeft === 0 ? 'Ended today' : 'Ended '.abs($daysLeft).' days ago';
            $statusLabel = 'Expired';
            $statusColor = '#b42318';
            $statusBg = '#fef3f2';
        } else {
            $daysLabel = $daysLeft.' days left';
            $statusLabel = 'Active';
            $statusColor = '#1f4f9f';
            $statusBg = '#eff6ff';
        }

        return [
            'tenant_id' => $row['tenant_id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'plan_name' => $row['plan_name'],
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'status_bg' => $statusBg,
            'expires_at_formatted' => $row['ends_at']->format('M j, Y'),
            'days_label' => $daysLabel,
            'days_left' => $daysLeft,
            'edit_url' => route('admin.customers.show', $row['tenant_id']),
        ];
    }
}
