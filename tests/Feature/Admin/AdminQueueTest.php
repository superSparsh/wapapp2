<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminQueueTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Queue Admin',
            'email' => 'queue-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_admin_can_view_queues_dashboard(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.queues.index'))
            ->assertOk()
            ->assertSee('Queues')
            ->assertSee('Failed jobs');
    }

    public function test_guest_cannot_view_queues(): void
    {
        $this->get(route('admin.queues.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_retry_and_forget_failed_job(): void
    {
        $uuid = (string) Str::uuid();
        $central = config('tenancy.database.central_connection');

        DB::connection($central)->table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'App\\Jobs\\ExampleJob',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => 'App\\Jobs\\ExampleJob', 'command' => ''],
            ]),
            'exception' => 'RuntimeException: boom',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.queues.forget', $uuid))
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid], $central);
    }
}
