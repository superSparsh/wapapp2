<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RetentionService
{
    /**
     * @param  array{q?: string, window?: string, status?: string}  $filters
     * @return array{kpi: array<string, int>, items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function report(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $filters = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'window' => (string) ($filters['window'] ?? '90'),
            'status' => (string) ($filters['status'] ?? ''),
        ];

        $rows = $this->buildRows();
        $kpi = [
            'total' => $rows->count(),
            'expiring_7' => $rows->where('days_left', '!==', null)->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] >= 0 && $r['days_left'] <= 7)->count(),
            'expiring_30' => $rows->where('days_left', '!==', null)->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] >= 0 && $r['days_left'] <= 30)->count(),
            'expiring_90' => $rows->where('days_left', '!==', null)->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] >= 0 && $r['days_left'] <= 90)->count(),
            'expired' => $rows->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] < 0)->count(),
            'suspended' => $rows->where('status', TenantStatus::Suspended->value)->count(),
            'no_validity' => $rows->whereNull('valid_until')->count(),
        ];

        $filtered = $this->applyFilters($rows, $filters);
        $page = max(1, $page);
        $slice = $filtered->forPage($page, $perPage)->values();

        $paginator = new Paginator($slice, $filtered->count(), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => array_filter($filters),
        ]);

        return [
            'kpi' => $kpi,
            'items' => $paginator,
            'filters' => $filters,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Tenant $tenant): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $validUntil = $this->parseValidUntil($settings);
        $notes = is_array($settings['retention_notes'] ?? null) ? $settings['retention_notes'] : [];

        return [
            'tenant' => $tenant->loadMissing('plan'),
            'settings' => $settings,
            'valid_until' => $validUntil,
            'days_left' => $validUntil === null
                ? null
                : (int) now()->startOfDay()->diffInDays($validUntil->copy()->startOfDay(), false),
            'notes' => array_values(array_reverse($notes)),
        ];
    }

    public function addNote(Tenant $tenant, string $note, string $actionType, string $adminName): Tenant
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $notes = is_array($settings['retention_notes'] ?? null) ? $settings['retention_notes'] : [];
        $notes[] = [
            'note' => trim($note),
            'action_type' => $actionType,
            'admin_name' => $adminName,
            'created_at' => now()->toIso8601String(),
        ];
        $settings['retention_notes'] = array_slice($notes, -100);
        $tenant->settings = $settings;
        $tenant->save();

        return $tenant->fresh() ?? $tenant;
    }

    /**
     * @param  array{q?: string, window?: string, status?: string}  $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $report = $this->report($filters, 1, 100_000);
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect($report['items']->items());

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['tenant_id', 'name', 'email', 'status', 'plan', 'valid_until', 'days_left', 'notes_count']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['name'],
                    $row['email'],
                    $row['status'],
                    $row['plan'],
                    $row['valid_until'],
                    $row['days_left'],
                    $row['notes_count'],
                ]);
            }
            fclose($out);
        }, 'retention-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildRows(): Collection
    {
        return Tenant::query()
            ->with('plan:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant): array {
                $settings = is_array($tenant->settings) ? $tenant->settings : [];
                $validUntil = $this->parseValidUntil($settings);
                $daysLeft = null;
                if ($validUntil !== null) {
                    $daysLeft = (int) now()->startOfDay()->diffInDays($validUntil->copy()->startOfDay(), false);
                }
                $notes = is_array($settings['retention_notes'] ?? null) ? $settings['retention_notes'] : [];

                return [
                    'id' => (string) $tenant->id,
                    'name' => (string) ($tenant->company_name ?: $tenant->name),
                    'email' => (string) ($tenant->email ?? ''),
                    'status' => $tenant->status?->value ?? '',
                    'plan' => $tenant->plan?->name ?? '—',
                    'valid_until' => $validUntil?->toDateString(),
                    'days_left' => $daysLeft,
                    'notes_count' => count($notes),
                    'tenant' => $tenant,
                ];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{q: string, window: string, status: string}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        if ($filters['q'] !== '') {
            $q = strtolower($filters['q']);
            $rows = $rows->filter(function (array $row) use ($q): bool {
                return str_contains(strtolower($row['id']), $q)
                    || str_contains(strtolower($row['name']), $q)
                    || str_contains(strtolower($row['email']), $q);
            });
        }

        if ($filters['status'] !== '' && TenantStatus::tryFrom($filters['status']) !== null) {
            $rows = $rows->where('status', $filters['status']);
        }

        $window = $filters['window'];
        $rows = match ($window) {
            '7' => $rows->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] >= 0 && $r['days_left'] <= 7),
            '30' => $rows->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] >= 0 && $r['days_left'] <= 30),
            '90' => $rows->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] >= 0 && $r['days_left'] <= 90),
            'expired' => $rows->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] < 0),
            'none' => $rows->whereNull('valid_until'),
            'suspended' => $rows->where('status', TenantStatus::Suspended->value),
            default => $rows,
        };

        return $rows->values();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function parseValidUntil(array $settings): ?Carbon
    {
        $raw = $settings['valid_until'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
