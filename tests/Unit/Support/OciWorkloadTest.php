<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\InboundWebhookEventType;
use App\Support\HorizonRole;
use App\Support\OciWorkload;
use Tests\TestCase;

class OciWorkloadTest extends TestCase
{
    public function test_queue_for_import_uses_import_queue_at_threshold(): void
    {
        config([
            'oci-workers.import_row_threshold' => 30000,
            'oci-workers.queues.import' => 'import',
            'queue.connections.redis.queue' => 'default',
        ]);

        $this->assertSame('import', OciWorkload::queueForImport(30000));
        $this->assertSame('import', OciWorkload::queueForImport(50000));
        $this->assertSame('default', OciWorkload::queueForImport(29999));
        $this->assertSame('default', OciWorkload::queueForImport(0));
    }

    public function test_inbound_event_queues(): void
    {
        config([
            'oci-workers.queues.status' => 'status',
            'oci-workers.queues.messages' => 'messages',
        ]);

        $this->assertSame('status', OciWorkload::queueForInboundEvent(InboundWebhookEventType::Status));
        $this->assertSame('messages', OciWorkload::queueForInboundEvent(InboundWebhookEventType::Message));
    }

    public function test_status_skip_sync_only_when_oci_enabled(): void
    {
        config([
            'oci-workers.enabled' => false,
            'oci-workers.status_queue_only' => true,
        ]);
        $this->assertFalse(OciWorkload::statusShouldSkipSync());

        config(['oci-workers.enabled' => true]);
        $this->assertTrue(OciWorkload::statusShouldSkipSync());

        config(['oci-workers.status_queue_only' => false]);
        $this->assertFalse(OciWorkload::statusShouldSkipSync());
    }

    public function test_estimate_csv_rows_skips_header(): void
    {
        $path = sys_get_temp_dir().'/oci-import-test-'.uniqid('', true).'.csv';
        file_put_contents($path, "phone,name\n111,a\n222,b\n333,c\n");

        try {
            $this->assertSame(3, OciWorkload::estimateCsvRows($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_horizon_role_filters_supervisors(): void
    {
        $config = [
            'defaults' => [
                'critical' => [],
                'campaign' => [],
                'status' => [],
                'import' => [],
                'messages' => [],
            ],
            'environments' => [
                'production' => [
                    'critical' => ['maxProcesses' => 1],
                    'campaign' => ['maxProcesses' => 1],
                    'status' => ['maxProcesses' => 1],
                    'import' => ['maxProcesses' => 1],
                    'messages' => ['maxProcesses' => 1],
                ],
            ],
        ];

        $web = HorizonRole::filterConfig($config, 'web');
        $this->assertArrayHasKey('critical', $web['defaults']);
        $this->assertArrayHasKey('messages', $web['defaults']);
        $this->assertArrayNotHasKey('campaign', $web['defaults']);
        $this->assertArrayNotHasKey('import', $web['environments']['production']);

        $heavy = HorizonRole::filterConfig($config, 'oci-heavy');
        $this->assertArrayHasKey('campaign', $heavy['defaults']);
        $this->assertArrayHasKey('status', $heavy['defaults']);
        $this->assertArrayHasKey('import', $heavy['defaults']);
        $this->assertArrayNotHasKey('critical', $heavy['defaults']);
        $this->assertArrayNotHasKey('messages', $heavy['environments']['production']);

        $all = HorizonRole::filterConfig($config, 'all');
        $this->assertCount(5, $all['defaults']);
    }

    public function test_horizon_role_prefers_process_environment(): void
    {
        putenv('HORIZON_ROLE=oci-heavy');
        try {
            $this->assertSame('oci-heavy', HorizonRole::current());
            $this->assertSame(HorizonRole::HEAVY, HorizonRole::allowedSupervisors());
        } finally {
            putenv('HORIZON_ROLE');
        }
    }
}
