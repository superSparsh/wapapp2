<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminListQuery;
use App\Domains\Admin\Support\ErrorModuleResolver;
use App\Enums\PlatformErrorType;
use App\Models\PlatformErrorLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformErrorLogService
{
    public function __construct(
        private readonly ErrorModuleResolver $resolver,
    ) {}

    /**
     * @return list<array{module: string, label: string, total: int, exception: int, api: int, job: int}>
     */
    public function hubCounts(): array
    {
        $modules = $this->resolver->modules();
        $counts = [];

        foreach ($modules as $key => $label) {
            $counts[$key] = [
                'module' => $key,
                'label' => $label,
                'total' => 0,
                'exception' => 0,
                'api' => 0,
                'job' => 0,
            ];
        }

        if (! $this->tableReady()) {
            return array_values($counts);
        }

        $rows = PlatformErrorLog::query()
            ->select('module', 'type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('module', 'type')
            ->get();

        foreach ($rows as $row) {
            $module = (string) $row->module;
            if (! isset($counts[$module])) {
                $counts[$module] = [
                    'module' => $module,
                    'label' => $this->resolver->label($module),
                    'total' => 0,
                    'exception' => 0,
                    'api' => 0,
                    'job' => 0,
                ];
            }

            $type = $row->type instanceof PlatformErrorType
                ? $row->type->value
                : (string) $row->type;
            $n = (int) $row->aggregate;
            $counts[$module]['total'] += $n;
            if (isset($counts[$module][$type])) {
                $counts[$module][$type] += $n;
            }
        }

        return array_values($counts);
    }

    /**
     * @param  array{
     *   type?: string|null,
     *   tenant_id?: string|null,
     *   q?: string,
     *   date_from?: string,
     *   date_to?: string,
     *   sort?: string,
     *   direction?: string
     * }  $filters
     * @return LengthAwarePaginator<int, PlatformErrorLog>
     */
    public function forModule(string $module, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = PlatformErrorLog::query()->where('module', $module);

        $type = (string) ($filters['type'] ?? 'all');
        if ($type !== '' && $type !== 'all') {
            $query->where('type', $type);
        }

        $tenantId = $filters['tenant_id'] ?? null;
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        AdminListQuery::applySearch(
            $query,
            (string) ($filters['q'] ?? ''),
            ['message', 'source', 'tenant_id'],
        );

        AdminListQuery::applyDateRange(
            $query,
            'occurred_at',
            (string) ($filters['date_from'] ?? ''),
            (string) ($filters['date_to'] ?? ''),
        );

        AdminListQuery::applySort(
            $query,
            (string) ($filters['sort'] ?? 'occurred_at'),
            (string) ($filters['direction'] ?? 'desc'),
            [
                'occurred_at' => 'occurred_at',
                'type' => 'type',
                'source' => 'source',
                'tenant_id' => 'tenant_id',
                'id' => 'id',
            ],
            'occurred_at',
        );

        if (($filters['sort'] ?? 'occurred_at') === 'occurred_at') {
            $query->orderByDesc('id');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function clearOlderThan(string $module, int $days = 30): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        $cutoff = Carbon::now()->subDays(max(1, $days));

        return PlatformErrorLog::query()
            ->where('module', $module)
            ->where('occurred_at', '<', $cutoff)
            ->delete();
    }

    private function tableReady(): bool
    {
        try {
            return Schema::connection(
                (string) config('tenancy.database.central_connection', config('database.default'))
            )->hasTable('platform_error_logs');
        } catch (\Throwable) {
            return false;
        }
    }
}
