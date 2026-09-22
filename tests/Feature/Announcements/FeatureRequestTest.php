<?php

declare(strict_types=1);

namespace Tests\Feature\Announcements;

use App\Domains\Alerts\Services\OperationalWhatsAppSender;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\AnnouncementFeatureRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FeatureRequestTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_tenant_can_list_active_features_and_request_activation(): void
    {
        $whatsApp = Mockery::mock(OperationalWhatsAppSender::class);
        $whatsApp->shouldReceive('sendTemplate')->once()->andReturn(true);
        $this->app->instance(OperationalWhatsAppSender::class, $whatsApp);

        config(['operational-alerts.developer_whatsapp_numbers' => ['919999999999']]);
        config(['operational-alerts.feature_request.whatsapp_template' => 'customer_new_feature_request_wapapp']);

        $announcement = Announcement::query()->create([
            'title' => 'AI Bot Pro',
            'body' => 'Request activation for AI Bot Pro.',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('announcements.features'))
            ->assertOk()
            ->assertSee('AI Bot Pro')
            ->assertSee('Request Activation / Demo');

        $this->actingAsTenantUser()
            ->post(route('announcements.features.request', $announcement))
            ->assertRedirect()
            ->assertSessionHas('status', 'Request submitted successfully!');

        $this->assertDatabaseHas('announcement_feature_requests', [
            'announcement_id' => $announcement->id,
            'tenant_id' => (string) $this->testTenant->id,
            'customer_email' => $this->testUser->email,
        ], (string) config('tenancy.database.central_connection', config('database.default')));

        $this->actingAsTenantUser()
            ->post(route('announcements.features.request', $announcement))
            ->assertRedirect()
            ->assertSessionHas('status', 'You have already submitted a request for this announcement.');

        $this->assertSame(1, AnnouncementFeatureRequest::query()->where('announcement_id', $announcement->id)->count());
    }

    public function test_admin_can_view_and_acknowledge_requests(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-features@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $announcement = Announcement::query()->create([
            'title' => 'Flows Plus',
            'body' => 'New flows.',
            'is_active' => true,
        ]);

        $request = AnnouncementFeatureRequest::query()->create([
            'announcement_id' => $announcement->id,
            'tenant_id' => (string) $this->testTenant->id,
            'user_id' => $this->testUser->id,
            'customer_email' => $this->testUser->email,
            'customer_name' => $this->testUser->name,
            'plan_name' => 'Growth',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.announcements.requests', $announcement))
            ->assertOk()
            ->assertSee($this->testUser->email);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.announcements.requests.acknowledge', $request))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue($request->fresh()->is_acknowledged);
    }
}
