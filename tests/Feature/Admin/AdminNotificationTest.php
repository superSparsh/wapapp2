<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Admin\Services\AdminNotificationService;
use App\Domains\Admin\Services\ModuleErrorRecorder;
use App\Enums\AdminNotificationType;
use App\Enums\PlatformErrorType;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AdminNotificationRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Notify Admin',
            'email' => 'notify-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_admin_header_shows_notification_bell(): void
    {
        app(AdminNotificationService::class)->notify(
            AdminNotificationType::System,
            'Test alert',
            'Something important happened.',
        );

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Test alert');
    }

    public function test_admin_can_view_notifications_index(): void
    {
        app(AdminNotificationService::class)->notify(
            AdminNotificationType::System,
            'Inbox alert',
            'Visible on index.',
            '/admin/queues',
        );

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee('Inbox alert');
    }

    public function test_missing_notification_read_redirects_to_index(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.notifications.read', 999999).'?redirect=/admin/queues')
            ->assertRedirect(route('admin.notifications.index'));
    }

    public function test_mark_all_read_clears_unread(): void
    {
        $service = app(AdminNotificationService::class);
        $service->notify(AdminNotificationType::System, 'One');
        $service->notify(AdminNotificationType::System, 'Two');

        $this->assertSame(2, $service->unreadCountFor($this->admin));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.notifications.mark-all-read'))
            ->assertRedirect();

        $this->assertSame(0, $service->unreadCountFor($this->admin));
        $this->assertSame(2, AdminNotificationRead::query()->where('admin_id', $this->admin->id)->count());
    }

    public function test_new_customer_notification_helper(): void
    {
        $n = app(AdminNotificationService::class)->notifyNewCustomer('acme', 'Acme Co', 'owner@acme.test');

        $this->assertNotNull($n);
        $this->assertSame(AdminNotificationType::NewCustomer, $n->type);
        $this->assertDatabaseHas('admin_notifications', [
            'type' => 'new_customer',
            'title' => 'New customer registered',
        ], config('tenancy.database.central_connection'));
    }

    public function test_platform_errors_are_throttled_in_notifications(): void
    {
        Cache::flush();
        $recorder = app(ModuleErrorRecorder::class);

        $recorder->record(PlatformErrorType::Job, 'Boom failed', module: 'campaigns', source: 'TestJob');
        $recorder->record(PlatformErrorType::Job, 'Boom failed', module: 'campaigns', source: 'TestJob');

        $notification = AdminNotification::query()->where('type', 'platform_error')->first();
        $this->assertNotNull($notification);
        $this->assertSame(1, AdminNotification::query()->where('type', 'platform_error')->count());

        $logId = $notification->data['error_log_id'] ?? null;
        $this->assertNotNull($logId);
        $this->assertTrue(
            str_ends_with((string) $notification->link, '/admin/errors/log/'.$logId),
            'Notification link should point to error detail page',
        );
    }

    public function test_opening_notification_marks_read_and_redirects(): void
    {
        $n = app(AdminNotificationService::class)->notify(
            AdminNotificationType::System,
            'Open me',
            link: '/admin/queues',
        );

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.notifications.read', $n->id).'?redirect=/admin/queues')
            ->assertRedirect('/admin/queues');

        $this->assertDatabaseHas('admin_notification_reads', [
            'admin_id' => $this->admin->id,
            'admin_notification_id' => $n->id,
        ], config('tenancy.database.central_connection'));
    }
}
