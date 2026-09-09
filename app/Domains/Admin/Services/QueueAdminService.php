<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminListQuery;
use App\Domains\Admin\Support\ErrorModuleResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class QueueAdminService
{
    public function __construct(
        private readonly ErrorModuleResolver $resolver,
    ) {}

    private function dbConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }

    /**
     * @param  array{
     *   module?: string|null,
     *   q?: string,
     *   queue?: string,
     *   date_from?: string,
     *   date_to?: string,
     *   sort?: string,
     *   direction?: string
     * }  $filters
     * @return array{
     *   pending: LengthAwarePaginator,
     *   failed: LengthAwarePaginator,
     *   connection: string,
     *   driver: string,
     *   pending_count: int,
     *   failed_count: int,
     *   module: ?string,
     *   modules: array<string, string>,
     *   filters: array<string, mixed>,
     *   queue_names: list<string>,
     *   sortOptions: list<array{value: string, label: string, direction: string}>
     * }
     */
    public function dashboard(int $page = 1, int $failedPage = 1, int $perPage = 25, array $filters = []): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        $module = $filters['module'] ?? null;
        if (is_string($module) && $module !== '' && ! $this->resolver->isValidModule($module)) {
            $module = null;
        }
        if ($module === '') {
            $module = null;
        }

        $normalized = [
            'module' => $module,
            'q' => trim((string) ($filters['q'] ?? '')),
            'queue' => trim((string) ($filters['queue'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'sort' => (string) ($filters['sort'] ?? 'id'),
            'direction' => strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        return [
            'pending' => $this->pendingJobs($page, $perPage, $normalized),
            'failed' => $this->failedJobs($failedPage, $perPage, $normalized),
            'connection' => $connection,
            'driver' => $driver,
            'pending_count' => $this->pendingCount($normalized),
            'failed_count' => $this->failedCount($normalized),
            'module' => $module,
            'modules' => $this->resolver->modules(),
            'filters' => $normalized,
            'queue_names' => $this->distinctQueueNames(),
            'sortOptions' => [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'id', 'label' => 'Oldest first', 'direction' => 'asc'],
                ['value' => 'queue', 'label' => 'Queue A–Z', 'direction' => 'asc'],
                ['value' => 'available_at', 'label' => 'Available soonest', 'direction' => 'asc'],
                ['value' => 'failed_at', 'label' => 'Failed newest', 'direction' => 'desc'],
            ],
        ];
    }

    public function retryFailed(string $uuid): void
    {
        $exit = Artisan::call('queue:retry', ['id' => [$uuid]]);
        if ($exit !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Unable to retry job.');
        }
    }

    public function retryAllFailed(): int
    {
        $before = $this->failedCount(['module' => null]);
        Artisan::call('queue:retry', ['id' => ['all']]);

        return max(0, $before - $this->failedCount(['module' => null]));
    }

    public function forgetFailed(string $uuid): void
    {
        $exit = Artisan::call('queue:forget', ['id' => $uuid]);
        if ($exit !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Unable to forget job.');
        }
    }

    public function flushFailed(): void
    {
        Artisan::call('queue:flush');
    }

    /**
     * @param  array{module?: string|null, q?: string, queue?: string, date_from?: string, date_to?: string}  $filters
     */
    private function pendingCount(array $filters): int
    {
        if (! Schema::connection($this->dbConnection())->hasTable('jobs')) {
            return 0;
        }

        $query = DB::connection($this->dbConnection())->table('jobs');
        $this->applyFilters($query, $filters, 'pending');

        return (int) $query->count();
    }

    /**
     * @param  array{module?: string|null, q?: string, queue?: string, date_from?: string, date_to?: string}  $filters
     */
    private function failedCount(array $filters): int
    {
        if (! Schema::connection($this->dbConnection())->hasTable('failed_jobs')) {
            return 0;
        }

        $query = DB::connection($this->dbConnection())->table('failed_jobs');
        $this->applyFilters($query, $filters, 'failed');

        return (int) $query->count();
    }

    /**
     * @param  array{module?: string|null, q?: string, queue?: string, date_from?: string, date_to?: string, sort?: string, direction?: string}  $filters
     */
    private function pendingJobs(int $page, int $perPage, array $filters): LengthAwarePaginator
    {
        if (! Schema::connection($this->dbConnection())->hasTable('jobs')) {
            return new Paginator([], 0, $perPage, $page);
        }

        $query = DB::connection($this->dbConnection())->table('jobs');
        $this->applyFilters($query, $filters, 'pending');
        $this->applySort($query, $filters, 'pending');

        $module = $filters['module'] ?? null;

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString()
            ->through(function (object $job) use ($module): array {
                $payload = json_decode((string) $job->payload, true) ?: [];
                $displayName = (string) ($payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Job'));

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'display_name' => $displayName,
                    'module' => $module ?? $this->resolver->fromDisplayName($displayName),
                    'attempts' => $job->attempts,
                    'available_at' => \format_ist($job->available_at),
                    'created_at' => \format_ist($job->created_at),
                ];
            });
    }

    /**
     * @param  array{module?: string|null, q?: string, queue?: string, date_from?: string, date_to?: string, sort?: string, direction?: string}  $filters
     */
    private function failedJobs(int $page, int $perPage, array $filters): LengthAwarePaginator
    {
        if (! Schema::connection($this->dbConnection())->hasTable('failed_jobs')) {
            return new Paginator([], 0, $perPage, $page);
        }

        $query = DB::connection($this->dbConnection())->table('failed_jobs');
        $this->applyFilters($query, $filters, 'failed');
        $this->applySort($query, $filters, 'failed');

        $module = $filters['module'] ?? null;

        return $query
            ->paginate($perPage, ['*'], 'failed_page', $page)
            ->withQueryString()
            ->through(function (object $job) use ($module): array {
                $payload = json_decode((string) $job->payload, true) ?: [];
                $displayName = (string) ($payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Job'));

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'display_name' => $displayName,
                    'module' => $module ?? $this->resolver->fromDisplayName($displayName),
                    'exception' => \Illuminate\Support\Str::limit((string) $job->exception, 280),
                    'failed_at' => \format_ist($job->failed_at),
                ];
            });
    }

    /**
     * @param  array{module?: string|null, q?: string, queue?: string, date_from?: string, date_to?: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters, string $tableKind): void
    {
        $this->applyModuleFilter($query, $filters['module'] ?? null);

        $queue = trim((string) ($filters['queue'] ?? ''));
        if ($queue !== '') {
            $query->where('queue', $queue);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $builder) use ($like, $tableKind): void {
                $builder->where('payload', 'like', $like)
                    ->orWhere('queue', 'like', $like);
                if ($tableKind === 'failed') {
                    $builder->orWhere('exception', 'like', $like)
                        ->orWhere('uuid', 'like', $like);
                }
            });
        }

        $dateColumn = $tableKind === 'failed' ? 'failed_at' : 'created_at';
        $isUnix = $tableKind === 'pending';

        AdminListQuery::applyDateRange(
            $query,
            $dateColumn,
            (string) ($filters['date_from'] ?? ''),
            (string) ($filters['date_to'] ?? ''),
            $isUnix,
        );
    }

    /**
     * @param  array{sort?: string, direction?: string}  $filters
     */
    private function applySort(Builder $query, array $filters, string $tableKind): void
    {
        $sort = (string) ($filters['sort'] ?? 'id');
        $direction = (string) ($filters['direction'] ?? 'desc');

        $map = $tableKind === 'failed'
            ? [
                'id' => 'id',
                'queue' => 'queue',
                'failed_at' => 'failed_at',
                'available_at' => 'failed_at',
            ]
            : [
                'id' => 'id',
                'queue' => 'queue',
                'available_at' => 'available_at',
                'failed_at' => 'created_at',
            ];

        AdminListQuery::applySort($query, $sort, $direction, $map, 'id');
    }

    private function applyModuleFilter(Builder $query, ?string $module): void
    {
        if ($module === null) {
            return;
        }

        $fragments = $this->resolver->payloadLikeFragments($module);
        if ($fragments === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($fragments): void {
            foreach ($fragments as $fragment) {
                $q->orWhere('payload', 'like', '%'.$fragment.'%');
            }
        });
    }

    /**
     * @return list<string>
     */
    private function distinctQueueNames(): array
    {
        $names = [];
        $central = $this->dbConnection();

        if (Schema::connection($central)->hasTable('jobs')) {
            $names = array_merge(
                $names,
                DB::connection($central)->table('jobs')->distinct()->orderBy('queue')->pluck('queue')->all()
            );
        }

        if (Schema::connection($central)->hasTable('failed_jobs')) {
            $names = array_merge(
                $names,
                DB::connection($central)->table('failed_jobs')->distinct()->orderBy('queue')->pluck('queue')->all()
            );
        }

        $names = array_values(array_unique(array_filter(array_map('strval', $names))));
        sort($names);

        return $names;
    }
}
