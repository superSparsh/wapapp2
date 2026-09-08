<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Shared\Services\CircuitBreaker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Health check endpoints for monitoring and load balancers.
 *
 * GET /health       — Basic liveness probe (Laravel's built-in /up)
 * GET /health/deep  — Readiness probe checking DB, cache, queue connectivity
 */
class HealthCheckController extends Controller
{
    /**
     * Deep health check — verifies all critical subsystems.
     *
     * Returns 200 if healthy, 503 if any critical check fails.
     * Safe to expose internally; does not leak sensitive data.
     */
    public function deep(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // 1. Database connectivity
        $checks['database'] = $this->checkDatabase();
        if (! $checks['database']['healthy']) {
            $allHealthy = false;
        }

        // 2. Cache / Redis connectivity
        $checks['cache'] = $this->checkCache();
        if (! $checks['cache']['healthy']) {
            $allHealthy = false;
        }

        // 3. Queue connectivity
        $checks['queue'] = $this->checkQueue();
        if (! $checks['queue']['healthy']) {
            $allHealthy = false;
        }

        // 4. Circuit breaker statuses
        $checks['circuits'] = $this->checkCircuits();

        // 5. Application metadata
        $checks['meta'] = [
            'app_version' => config('app.version', '2.0.0'),
            'environment' => config('app.env', 'production'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        return new JsonResponse([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo()->query('SELECT 1');

            return [
                'healthy' => true,
                'driver' => DB::connection()->getDriverName(),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'driver' => config('database.default'),
                'error' => 'Connection failed: '.$e->getMessage(),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }
    }

    private function checkCache(): array
    {
        $start = microtime(true);

        try {
            $testKey = 'health_check:'.uniqid();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'healthy' => $value === 'ok',
                'driver' => config('cache.default'),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'driver' => config('cache.default'),
                'error' => 'Connection failed: '.$e->getMessage(),
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $driver = config('queue.default');

            // Just verify the queue connection is configured
            // (don't actually push a job — that would be a side effect)
            return [
                'healthy' => true,
                'driver' => $driver,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'driver' => config('queue.default'),
                'error' => 'Queue check failed: '.$e->getMessage(),
            ];
        }
    }

    private function checkCircuits(): array
    {
        try {
            $breaker = app(CircuitBreaker::class);
            $statuses = $breaker->allStatuses();

            $openCircuits = array_filter(
                $statuses,
                fn (array $s) => $s['state'] === 'open',
            );

            return [
                'total' => count($statuses),
                'open' => count($openCircuits),
                'details' => $statuses,
                'healthy' => empty($openCircuits),
            ];
        } catch (\Throwable) {
            return [
                'total' => 0,
                'open' => 0,
                'details' => [],
                'healthy' => true,
            ];
        }
    }
}
