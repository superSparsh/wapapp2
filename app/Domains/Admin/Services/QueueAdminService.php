<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\ErrorModuleResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
     * @return array{
     *   pending: LengthAwarePaginator,
     *   failed: LengthAwarePaginator,
     *   connection: string,
     *   driver: string,
     *   pending_count: int,
     *   failed_count: int,
     *   module: ?string,
     *   modules: array<string, string>
     * }
     */
    public function dashboard(int $page = 1, int $failedPage = 1, int $perPage = 25, ?string $module = null): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        if ($module !== null && $module !== '' && ! $this->resolver->isValidModule($module)) {
            $module = null;
        }
        if ($module === '') {
            $module = null;
        }

        return [
            'pending' => $this->pendingJobs($page, $perPage, $module),
            'failed' => $this->failedJobs($failedPage, $perPage, $module),
            'connection' => $connection,
            'driver' => $driver,
            'pending_count' => $this->pendingCount($module),
            'failed_count' => $this->failedCount($module),
            'module' => $module,
            'modules' => $this->resolver->modules(),
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
        $before = $this->failedCount(null);
        Artisan::call('queue:retry', ['id' => ['all']]);

        return max(0, $before - $this->failedCount(null));
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

    private function pendingCount(?string $module): int
    {
        if (! Schema::connection($this->dbConnection())->hasTable('jobs')) {
            return 0;
        }

        $query = DB::connection($this->dbConnection())->table('jobs');
        $this->applyModuleFilter($query, $module);

        return (int) $query->count();
    }

    private function failedCount(?string $module): int
    {
        if (! Schema::connection($this->dbConnection())->hasTable('failed_jobs')) {
            return 0;
        }

        $query = DB::connection($this->dbConnection())->table('failed_jobs');
        $this->applyModuleFilter($query, $module);

        return (int) $query->count();
    }

    private function pendingJobs(int $page, int $perPage, ?string $module): LengthAwarePaginator
    {
        if (! Schema::connection($this->dbConnection())->hasTable('jobs')) {
            return new Paginator([], 0, $perPage, $page);
        }

        $query = DB::connection($this->dbConnection())->table('jobs')->orderByDesc('id');
        $this->applyModuleFilter($query, $module);

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(function (object $job) use ($module): array {
                $payload = json_decode((string) $job->payload, true) ?: [];
                $displayName = (string) ($payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Job'));

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'display_name' => $displayName,
                    'module' => $module ?? $this->resolver->fromDisplayName($displayName),
                    'attempts' => $job->attempts,
                    'available_at' => $job->available_at,
                    'created_at' => $job->created_at,
                ];
            });
    }

    private function failedJobs(int $page, int $perPage, ?string $module): LengthAwarePaginator
    {
        if (! Schema::connection($this->dbConnection())->hasTable('failed_jobs')) {
            return new Paginator([], 0, $perPage, $page);
        }

        $query = DB::connection($this->dbConnection())->table('failed_jobs')->orderByDesc('id');
        $this->applyModuleFilter($query, $module);

        return $query
            ->paginate($perPage, ['*'], 'failed_page', $page)
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
                    'failed_at' => $job->failed_at,
                ];
            });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyModuleFilter($query, ?string $module): void
    {
        if ($module === null) {
            return;
        }

        $fragments = $this->resolver->payloadLikeFragments($module);
        if ($fragments === []) {
            // No known classes — match nothing for this module.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($q) use ($fragments): void {
            foreach ($fragments as $fragment) {
                $q->orWhere('payload', 'like', '%'.$fragment.'%');
            }
        });
    }
}
