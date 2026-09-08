<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class DataPurgeService
{
    /**
     * @param  array{q?: string}  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function candidates(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $rows = Tenant::query()
            ->with('plan:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant): ?array {
                $settings = is_array($tenant->settings) ? $tenant->settings : [];
                $validUntil = null;
                if (! empty($settings['valid_until'])) {
                    try {
                        $validUntil = Carbon::parse((string) $settings['valid_until'])->startOfDay();
                    } catch (\Throwable) {
                        $validUntil = null;
                    }
                }

                $expired = $validUntil !== null && $validUntil->lt(now()->startOfDay());
                $suspended = $tenant->status === TenantStatus::Suspended;
                $marked = ! empty($settings['purge_requested_at']);

                if (! $expired && ! $suspended && ! $marked) {
                    return null;
                }

                return [
                    'id' => (string) $tenant->id,
                    'name' => (string) ($tenant->company_name ?: $tenant->name),
                    'email' => (string) ($tenant->email ?? ''),
                    'status' => $tenant->status?->value ?? '',
                    'plan' => $tenant->plan?->name ?? '—',
                    'valid_until' => $validUntil?->toDateString(),
                    'purge_requested_at' => $settings['purge_requested_at'] ?? null,
                    'reason' => $marked ? 'Marked for purge' : ($expired ? 'Expired' : 'Suspended'),
                ];
            })
            ->filter()
            ->values();

        if ($q !== '') {
            $needle = strtolower($q);
            $rows = $rows->filter(fn (array $row) => str_contains(strtolower($row['id'].$row['name'].$row['email']), $needle))->values();
        }

        $page = max(1, $page);
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter(['q' => $q])],
        );

        return ['items' => $paginator, 'filters' => ['q' => $q]];
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Tenant $tenant): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        return [
            'tenant' => $tenant->loadMissing('plan'),
            'settings' => $settings,
            'valid_until' => $settings['valid_until'] ?? null,
            'purge_requested_at' => $settings['purge_requested_at'] ?? null,
        ];
    }

    public function markForPurge(Tenant $tenant, string $adminName): Tenant
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['purge_requested_at'] = now()->toIso8601String();
        $settings['purge_requested_by'] = $adminName;
        $tenant->settings = $settings;
        $tenant->status = TenantStatus::Suspended;
        $tenant->suspended_at = $tenant->suspended_at ?? now();
        $tenant->save();

        return $tenant->fresh() ?? $tenant;
    }

    public function clearPurgeMark(Tenant $tenant): Tenant
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        unset($settings['purge_requested_at'], $settings['purge_requested_by']);
        $tenant->settings = $settings;
        $tenant->save();

        return $tenant->fresh() ?? $tenant;
    }
}
