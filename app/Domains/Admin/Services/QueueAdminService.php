<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class QueueAdminService
{
    private function dbConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }

    /**
     * @return array{pending: LengthAwarePaginator, failed: LengthAwarePaginator, connection: string, driver: string, pending_count: int, failed_count: int}
     */
    public function dashboard(int $page = 1, int $failedPage = 1, int $perPage = 25): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);

        return [
            'pending' => $this->pendingJobs($page, $perPage),
            'failed' => $this->failedJobs($failedPage, $perPage),
            'connection' => $connection,
            'driver' => $driver,
            'pending_count' => $this->pendingCount(),
            'failed_count' => $this->failedCount(),
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
        $before = $this->failedCount();
        Artisan::call('queue:retry', ['id' => ['all']]);

        return max(0, $before - $this->failedCount());
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

    private function pendingCount(): int
    {
        if (! Schema::connection($this->dbConnection())->hasTable('jobs')) {
            return 0;
        }

        return (int) DB::connection($this->dbConnection())->table('jobs')->count();
    }

    private function failedCount(): int
    {
        if (! Schema::connection($this->dbConnection())->hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::connection($this->dbConnection())->table('failed_jobs')->count();
    }

    private function pendingJobs(int $page, int $perPage): LengthAwarePaginator
    {
        if (! Schema::connection($this->dbConnection())->hasTable('jobs')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page);
        }

        return DB::connection($this->dbConnection())->table('jobs')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(function (object $job): array {
                $payload = json_decode((string) $job->payload, true) ?: [];

                return [
                    'id' => $job->id,
                    'queue' => $job->queue,
                    'display_name' => $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Job'),
                    'attempts' => $job->attempts,
                    'available_at' => $job->available_at,
                    'created_at' => $job->created_at,
                ];
            });
    }

    private function failedJobs(int $page, int $perPage): LengthAwarePaginator
    {
        if (! Schema::connection($this->dbConnection())->hasTable('failed_jobs')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage, $page);
        }

        return DB::connection($this->dbConnection())->table('failed_jobs')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'failed_page', $page)
            ->through(function (object $job): array {
                $payload = json_decode((string) $job->payload, true) ?: [];

                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'connection' => $job->connection,
                    'display_name' => $payload['displayName'] ?? ($payload['data']['commandName'] ?? 'Job'),
                    'exception' => \Illuminate\Support\Str::limit((string) $job->exception, 280),
                    'failed_at' => $job->failed_at,
                ];
            });
    }
}
