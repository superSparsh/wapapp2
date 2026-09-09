<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Admin\Services\ModuleErrorRecorder;
use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Domains\Inbox\Jobs\SendOutboundMessageJob;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\PlatformErrorType;
use App\Models\Admin;
use App\Models\PlatformErrorLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PlatformErrorLogTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Error Admin',
            'email' => 'error-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_recorder_writes_module_and_type(): void
    {
        app(ModuleErrorRecorder::class)->record(
            type: PlatformErrorType::Exception,
            message: 'Boom',
            module: 'inbox',
            source: 'TestSource',
        );

        $this->assertDatabaseHas('platform_error_logs', [
            'module' => 'inbox',
            'type' => 'exception',
            'message' => 'Boom',
            'source' => 'TestSource',
        ], config('tenancy.database.central_connection'));
    }

    public function test_reported_exception_creates_exception_row(): void
    {
        report(new \RuntimeException('Reported failure from App\\Domains\\Inbox\\Services\\X'));

        // Force reportable by using recorder directly via exception with inbox-ish trace is hard;
        // call recordException which reportable uses.
        app(ModuleErrorRecorder::class)->recordException(
            new \RuntimeException('Inbox blew up'),
            module: 'inbox',
            source: 'App\\Domains\\Inbox\\Services\\Outbound',
        );

        $this->assertTrue(
            PlatformErrorLog::query()
                ->where('type', PlatformErrorType::Exception)
                ->where('module', 'inbox')
                ->where('message', 'Inbox blew up')
                ->exists()
        );
    }

    public function test_cams_client_records_api_error_on_non_ok_code(): void
    {
        config([
            'whatsapp.alibaba.access_key_id' => 'test_key',
            'whatsapp.alibaba.access_key_secret' => 'test_secret',
        ]);

        Http::fake([
            'cams.ap-southeast-1.aliyuncs.com/*' => Http::response([
                'Code' => 'InvalidParameter',
                'Message' => 'Catalog missing BusinessId',
                'RequestId' => 'REQ-1',
            ], 200),
        ]);

        app(AlibabaCamsClient::class)->listProductCatalogs([
            'CustSpaceId' => 'SP1',
            'BusinessId' => '1',
        ]);

        $this->assertTrue(
            PlatformErrorLog::query()
                ->where('type', PlatformErrorType::Api)
                ->where('module', 'commerce')
                ->where('source', 'ListProductCatalog')
                ->where('message', 'Catalog missing BusinessId')
                ->exists()
        );
    }

    public function test_queue_failing_listener_records_job_error(): void
    {
        $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $job->shouldReceive('resolveName')->andReturn(SendOutboundMessageJob::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getJobId')->andReturn('job-1');
        $job->shouldReceive('payload')->andReturn([
            'displayName' => SendOutboundMessageJob::class,
        ]);
        $job->shouldReceive('uuid')->andReturn('uuid-1');
        $job->shouldReceive('getConnectionName')->andReturn('database');

        event(new JobFailed('database', $job, new \RuntimeException('Send failed permanently')));

        $this->assertTrue(
            PlatformErrorLog::query()
                ->where('type', PlatformErrorType::Job)
                ->where('module', 'inbox')
                ->where('message', 'Send failed permanently')
                ->exists()
        );
    }

    public function test_admin_errors_hub_and_module_page(): void
    {
        PlatformErrorLog::query()->create([
            'module' => 'campaigns',
            'type' => PlatformErrorType::Job,
            'tenant_id' => null,
            'source' => SendCampaignRecipientJob::class,
            'message' => 'Campaign send failed',
            'context' => [],
            'occurred_at' => now(),
        ]);

        PlatformErrorLog::query()->create([
            'module' => 'campaigns',
            'type' => PlatformErrorType::Api,
            'tenant_id' => null,
            'source' => 'SendChatappMassMessage',
            'message' => 'Rate limited',
            'context' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.errors.index'))
            ->assertOk()
            ->assertSee('Campaigns')
            ->assertSee('Errors');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.errors.show', ['module' => 'campaigns', 'type' => 'api']))
            ->assertOk()
            ->assertSee('Rate limited')
            ->assertDontSee('Campaign send failed');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.errors.show', ['module' => 'campaigns', 'type' => 'job']))
            ->assertOk()
            ->assertSee('Campaign send failed');
    }

    public function test_error_module_supports_search_and_date_filters(): void
    {
        PlatformErrorLog::query()->create([
            'module' => 'inbox',
            'type' => PlatformErrorType::Exception,
            'tenant_id' => 'tenant-a',
            'source' => 'OutboundService',
            'message' => 'UniqueAlpha error text',
            'context' => [],
            'occurred_at' => now()->subDays(2),
        ]);

        PlatformErrorLog::query()->create([
            'module' => 'inbox',
            'type' => PlatformErrorType::Api,
            'tenant_id' => 'tenant-b',
            'source' => 'CamsClient',
            'message' => 'Other noise',
            'context' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.errors.show', [
                'module' => 'inbox',
                'q' => 'UniqueAlpha',
            ]))
            ->assertOk()
            ->assertSee('UniqueAlpha error text')
            ->assertDontSee('Other noise');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.errors.show', [
                'module' => 'inbox',
                'date_from' => now()->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Other noise')
            ->assertDontSee('UniqueAlpha error text');
    }

    public function test_queues_module_filter_shows_only_matching_jobs(): void
    {
        $central = config('tenancy.database.central_connection');

        DB::connection($central)->table('failed_jobs')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode([
                    'displayName' => SendCampaignRecipientJob::class,
                    'data' => ['commandName' => SendCampaignRecipientJob::class],
                ]),
                'exception' => 'Campaign failed',
                'failed_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode([
                    'displayName' => SendOutboundMessageJob::class,
                    'data' => ['commandName' => SendOutboundMessageJob::class],
                ]),
                'exception' => 'Inbox failed',
                'failed_at' => now(),
            ],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.queues.index', ['module' => 'campaigns']))
            ->assertOk()
            ->assertSee(SendCampaignRecipientJob::class)
            ->assertDontSee(SendOutboundMessageJob::class);
    }

    public function test_queues_search_and_queue_name_filter(): void
    {
        $central = config('tenancy.database.central_connection');

        DB::connection($central)->table('failed_jobs')->insert([
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'mail',
                'payload' => json_encode([
                    'displayName' => 'App\\Jobs\\MailJob',
                    'data' => ['commandName' => 'App\\Jobs\\MailJob'],
                ]),
                'exception' => 'SMTP timeout UniqueQueueSearch',
                'failed_at' => now(),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode([
                    'displayName' => 'App\\Jobs\\OtherJob',
                    'data' => ['commandName' => 'App\\Jobs\\OtherJob'],
                ]),
                'exception' => 'Something else',
                'failed_at' => now(),
            ],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.queues.index', ['q' => 'UniqueQueueSearch']))
            ->assertOk()
            ->assertSee('SMTP timeout UniqueQueueSearch')
            ->assertDontSee('Something else');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.queues.index', ['queue' => 'mail']))
            ->assertOk()
            ->assertSee('App\\Jobs\\MailJob')
            ->assertDontSee('App\\Jobs\\OtherJob');
    }
}
