<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Services\CircuitBreaker;
use App\Shared\Services\CircuitOpenException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = app(CircuitBreaker::class);
        // Reset all circuits before each test
        Cache::forget('circuit:registry');
        Cache::forget('circuit:test:state');
        Cache::forget('circuit:test:failures');
        Cache::forget('circuit:test:last_failure_time');
        Cache::forget('circuit:test:half_open_successes');
    }

    public function test_circuit_starts_closed(): void
    {
        $this->assertTrue($this->breaker->isClosed('test'));
        $this->assertFalse($this->breaker->isOpen('test'));
    }

    public function test_successful_call_keeps_circuit_closed(): void
    {
        $result = $this->breaker->call('test', fn () => 'ok');

        $this->assertSame('ok', $result);
        $this->assertTrue($this->breaker->isClosed('test'));
    }

    public function test_circuit_opens_after_failure_threshold(): void
    {
        // Cause 3 failures to open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->breaker->call('test', function () {
                    throw new \RuntimeException('fail');
                }, failureThreshold: 3);
            } catch (\RuntimeException) {
                // expected
            }
        }

        // Circuit should now be open
        $this->assertTrue($this->breaker->isOpen('test'));

        // Next call should throw CircuitOpenException
        $this->expectException(CircuitOpenException::class);
        $this->breaker->call('test', fn () => 'should not execute');
    }

    public function test_open_circuit_throws_circuit_open_exception(): void
    {
        // Force circuit open
        Cache::put('circuit:test:state', 'open', 600);
        Cache::put('circuit:test:last_failure_time', time(), 600);

        $this->expectException(CircuitOpenException::class);

        $this->breaker->call('test', fn () => 'should not execute');
    }

    public function test_reset_closes_circuit(): void
    {
        Cache::put('circuit:test:state', 'open', 600);
        Cache::put('circuit:test:failures', 5, 600);

        $this->assertTrue($this->breaker->isOpen('test'));

        $this->breaker->reset('test');

        $this->assertTrue($this->breaker->isClosed('test'));
        $this->assertSame(0, $this->breaker->getFailureCount('test'));
    }

    public function test_failure_count_increments(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            try {
                $this->breaker->call('test', function () {
                    throw new \RuntimeException('fail');
                }, failureThreshold: 10);
            } catch (\RuntimeException) {
                // expected
            }

            $this->assertSame($i, $this->breaker->getFailureCount('test'));
        }
    }

    public function test_success_resets_failure_count(): void
    {
        // Cause 2 failures
        for ($i = 0; $i < 2; $i++) {
            try {
                $this->breaker->call('test', fn () => throw new \RuntimeException, failureThreshold: 10);
            } catch (\RuntimeException) {
            }
        }

        $this->assertSame(2, $this->breaker->getFailureCount('test'));

        // Success resets counter
        $this->breaker->call('test', fn () => 'ok');

        $this->assertSame(0, $this->breaker->getFailureCount('test'));
    }

    public function test_all_statuses_returns_registered_circuits(): void
    {
        $this->breaker->call('test', fn () => 'ok');

        $statuses = $this->breaker->allStatuses();

        $this->assertArrayHasKey('test', $statuses);
        $this->assertSame('closed', $statuses['test']['state']);
    }

    public function test_circuit_open_exception_has_correct_error_code(): void
    {
        $e = new CircuitOpenException('Meta API unavailable');

        $this->assertSame('SYS_CIRCUIT_OPEN', $e->getErrorCode()->value);
        $this->assertSame(503, $e->getHttpStatus());
    }
}
