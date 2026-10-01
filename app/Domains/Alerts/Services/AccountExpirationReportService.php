<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Monthly account expiry report from central 2.0 tenants only
 * (tenants.settings.valid_until — same source as Admin → Retention).
 *
 * Buckets match the legacy email layout: ≤30d, 31–90d, expired.
 */
class AccountExpirationReportService
{
    /**
     * @return array{
     *     generated_at: string,
     *     expired: list<array<string, mixed>>,
     *     expiring_within_30: list<array<string, mixed>>,
     *     expiring_30_90: list<array<string, mixed>>,
     *     meta: array{tenants_scanned: int, with_validity: int, beyond_90: int, missing_validity: int}
     * }
     */
    public function buildReport(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $rows = $this->collectRows($asOf);

        $expired = $rows['items']
            ->filter(fn (array $row): bool => $row['days_left'] <= 0)
            ->sortByDesc('ends_at_ts')
            ->values()
            ->map(fn (array $row): array => $this->mapRow($row, 'expired'))
            ->all();

        $within30 = $rows['items']
            ->filter(fn (array $row): bool => $row['days_left'] >= 1 && $row['days_left'] <= 30)
            ->sortBy('ends_at_ts')
            ->values()
            ->map(fn (array $row): array => $this->mapRow($row, 'expiring'))
            ->all();

        $within90 = $rows['items']
            ->filter(fn (array $row): bool => $row['days_left'] >= 31 && $row['days_left'] <= 90)
            ->sortBy('ends_at_ts')
            ->values()
            ->map(fn (array $row): array => $this->mapRow($row, 'expiring'))
            ->all();

        return [
            'generated_at' => $asOf->format('F j, Y'),
            'expired' => $expired,
            'expiring_within_30' => $within30,
            'expiring_30_90' => $within90,
            'meta' => $rows['meta'],
        ];
    }

    /**
     * @return array{
     *     items: Collection<int, array{tenant_id: string, name: string, email: string, plan_name: string, ends_at: Carbon, ends_at_ts: int, days_left: int}>,
     *     meta: array{tenants_scanned: int, with_validity: int, beyond_90: int, missing_validity: int}
     * }
     */
    private function collectRows(Carbon $asOf): array
    {
        $out = collect();
        $scanned = 0;
        $withValidity = 0;
        $beyond90 = 0;
        $missing = 0;

        Tenant::query()
            ->whereIn('status', [TenantStatus::Active, TenantStatus::Suspended])
            ->with('plan:id,name')
            ->orderBy('id')
            ->cursor()
            ->each(function (Tenant $tenant) use ($asOf, $out, &$scanned, &$withValidity, &$beyond90, &$missing): void {
                $scanned++;
                $endsAt = $this->parseValidUntil($tenant);
                if ($endsAt === null) {
                    $missing++;

                    return;
                }

                $withValidity++;
                $daysLeft = (int) $asOf->copy()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false);
                if ($daysLeft > 90) {
                    $beyond90++;

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

        return [
            'items' => $out,
            'meta' => [
                'tenants_scanned' => $scanned,
                'with_validity' => $withValidity,
                'beyond_90' => $beyond90,
                'missing_validity' => $missing,
            ],
        ];
    }

    /**
     * Central tenants.settings.valid_until only (2.0 admin source of truth).
     */
    private function parseValidUntil(Tenant $tenant): ?Carbon
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
        ];
    }
}
