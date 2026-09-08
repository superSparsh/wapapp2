<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Circuit breaker for external API calls (Meta WhatsApp, Razorpay, Shopify, etc.).
 *
 * States:
 *   CLOSED   — Normal operation, requests flow through.
 *   OPEN     — Failures exceeded threshold; calls are blocked immediately.
 *   HALF_OPEN — After cooldown, one probe request is allowed through.
 *              Success → CLOSED. Failure → OPEN again.
 *
 * Usage:
 *   $result = app(CircuitBreaker::class)->call('meta_api', function () {
 *       return Http::post('https://graph.facebook.com/...', $payload);
 *   });
 */
class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';

    private const STATE_OPEN = 'open';

    private const STATE_HALF_OPEN = 'half_open';

    /**
     * Execute a callable through the circuit breaker.
     *
     * @param  string  $name  Unique circuit name (e.g. "meta_api", "razorpay")
     * @param  Closure  $callback  The operation to protect
     * @param  int  $failureThreshold  Failures before opening the circuit
     * @param  int  $cooldownSeconds  Seconds to wait before half-open probe
     * @param  int  $successThreshold  Successes in half-open to close circuit
     * @return mixed
     *
     * @throws CircuitOpenException  When the circuit is open and calls are blocked
     */
    public function call(
        string $name,
        Closure $callback,
        int $failureThreshold = 5,
        int $cooldownSeconds = 60,
        int $successThreshold = 2,
    ): mixed {
        $state = $this->getState($name);

        // Register circuit on first call
        $this->registerCircuit($name);

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset($name, $cooldownSeconds)) {
                $this->setState($name, self::STATE_HALF_OPEN);
                $state = self::STATE_HALF_OPEN;
            } else {
                Log::warning("Circuit breaker [{$name}] is OPEN — call blocked.", [
                    'failures' => $this->getFailureCount($name),
                ]);

                throw new CircuitOpenException("Circuit [{$name}] is open. External service temporarily unavailable.");
            }
        }

        try {
            $result = $callback();

            $this->onSuccess($name, $state, $successThreshold);

            return $result;
        } catch (Throwable $e) {
            $this->onFailure($name, $failureThreshold, $cooldownSeconds);

            throw $e;
        }
    }

    /**
     * Check if a circuit is currently open (blocking calls).
     */
    public function isOpen(string $name): bool
    {
        return $this->getState($name) === self::STATE_OPEN;
    }

    /**
     * Check if a circuit is closed (allowing calls).
     */
    public function isClosed(string $name): bool
    {
        return $this->getState($name) === self::STATE_CLOSED;
    }

    /**
     * Get the current state of a circuit.
     */
    public function getState(string $name): string
    {
        return Cache::get("circuit:{$name}:state", self::STATE_CLOSED);
    }

    /**
     * Get the current failure count for a circuit.
     */
    public function getFailureCount(string $name): int
    {
        return (int) Cache::get("circuit:{$name}:failures", 0);
    }

    /**
     * Manually reset a circuit to closed state.
     */
    public function reset(string $name): void
    {
        Cache::forget("circuit:{$name}:state");
        Cache::forget("circuit:{$name}:failures");
        Cache::forget("circuit:{$name}:last_failure_time");
        Cache::forget("circuit:{$name}:half_open_successes");
    }

    /**
     * Get status of all known circuits (for monitoring / health checks).
     */
    public function allStatuses(): array
    {
        $circuits = Cache::get('circuit:registry', []);
        $statuses = [];

        foreach ($circuits as $name) {
            $statuses[$name] = [
                'state' => $this->getState($name),
                'failures' => $this->getFailureCount($name),
            ];
        }

        return $statuses;
    }

    // ── Private helpers ──

    private function onSuccess(string $name, string $previousState, int $successThreshold): void
    {
        if ($previousState === self::STATE_HALF_OPEN) {
            $successes = (int) Cache::get("circuit:{$name}:half_open_successes", 0) + 1;
            Cache::put("circuit:{$name}:half_open_successes", $successes, 300);

            if ($successes >= $successThreshold) {
                Log::info("Circuit breaker [{$name}] closed after successful probe.");
                $this->reset($name);
            }

            return;
        }

        // Normal success in closed state — reset failure counter
        Cache::forget("circuit:{$name}:failures");
    }

    private function onFailure(string $name, int $failureThreshold, int $cooldownSeconds): void
    {
        $state = $this->getState($name);

        if ($state === self::STATE_HALF_OPEN) {
            // Probe failed — reopen immediately
            $this->setState($name, self::STATE_OPEN);
            Cache::put("circuit:{$name}:last_failure_time", time(), $cooldownSeconds * 2);
            Cache::forget("circuit:{$name}:half_open_successes");

            Log::warning("Circuit breaker [{$name}] reopened after failed probe.");

            return;
        }

        $failures = $this->getFailureCount($name) + 1;
        Cache::put("circuit:{$name}:failures", $failures, $cooldownSeconds * 3);

        $this->registerCircuit($name);

        if ($failures >= $failureThreshold) {
            $this->setState($name, self::STATE_OPEN);
            Cache::put("circuit:{$name}:last_failure_time", time(), $cooldownSeconds * 2);

            Log::warning("Circuit breaker [{$name}] OPEN after {$failures} failures.", [
                'threshold' => $failureThreshold,
                'cooldown' => $cooldownSeconds,
            ]);
        }
    }

    private function shouldAttemptReset(string $name, int $cooldownSeconds): bool
    {
        $lastFailure = (int) Cache::get("circuit:{$name}:last_failure_time", 0);

        return $lastFailure > 0 && (time() - $lastFailure) >= $cooldownSeconds;
    }

    private function setState(string $name, string $state): void
    {
        Cache::put("circuit:{$name}:state", $state, 600);
        $this->registerCircuit($name);
    }

    /**
     * Register the circuit name so allStatuses() can enumerate it.
     */
    private function registerCircuit(string $name): void
    {
        $circuits = Cache::get('circuit:registry', []);

        if (! in_array($name, $circuits, true)) {
            $circuits[] = $name;
            Cache::put('circuit:registry', $circuits, 86400);
        }
    }
}
