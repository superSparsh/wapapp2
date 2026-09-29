<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Domains\Admin\Support\AdminSession;
use App\Enums\TenantUserAccountType;
use App\Models\Admin;
use App\Models\TenantUserAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenant();

        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->testUser->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'phone' => $this->testUser->phone,
            ],
        );

        $this->admin = Admin::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin-maint@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_admin_can_enable_maintenance_and_customer_site_is_blocked(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.maintenance.update'), [
                'enabled' => '1',
                'message' => 'Upgrading systems tonight.',
                'modules' => [
                    'inbound_webhooks' => '1',
                    'chatbot' => '1',
                    'campaigns' => '1',
                    'drip' => '1',
                    'outbound_messages' => '1',
                    'customer_api' => '0',
                ],
            ])
            ->assertRedirect();

        $this->assertTrue(app(MaintenanceModeService::class)->enabled());
        $this->assertTrue(app(MaintenanceModeService::class)->moduleEnabled('chatbot'));
        $this->assertFalse(app(MaintenanceModeService::class)->moduleEnabled('customer_api'));

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertStatus(503)
            ->assertSee('Upgrading systems tonight.', false);

        $this->get(route('signup.step-1'))
            ->assertStatus(503)
            ->assertSee('Upgrading systems tonight.', false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.maintenance.edit'))
            ->assertOk();
    }

    public function test_customer_admin_account_keeps_access_during_maintenance(): void
    {
        app(MaintenanceModeService::class)->save([
            'enabled' => true,
            'message' => 'Upgrading systems tonight.',
            'modules' => ['chatbot' => true],
        ]);

        Admin::query()->create([
            'name' => 'Customer Admin',
            'email' => $this->testUser->email,
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_admin_impersonation_keeps_access_during_maintenance(): void
    {
        app(MaintenanceModeService::class)->save([
            'enabled' => true,
            'message' => 'Upgrading systems tonight.',
            'modules' => ['chatbot' => true],
        ]);

        $this->withSession([
            AdminSession::IMPERSONATION => [
                'admin_id' => (int) $this->admin->id,
                'tenant_id' => (string) $this->testTenant->id,
                'tenant_name' => (string) $this->testTenant->name,
                'admin_name' => (string) $this->admin->name,
                'admin_email' => strtolower((string) $this->admin->email),
            ],
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_regular_customer_login_is_rejected_during_maintenance(): void
    {
        app(MaintenanceModeService::class)->save([
            'enabled' => true,
            'message' => 'Upgrading systems tonight.',
            'modules' => ['chatbot' => true],
        ]);

        $this->post(route('login.store'), [
            'email' => $this->testUser->email,
            'password' => 'password',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_maintenance_can_be_turned_off(): void
    {
        app(MaintenanceModeService::class)->save([
            'enabled' => true,
            'message' => 'Down',
            'modules' => ['chatbot' => true],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.maintenance.update'), [
                'enabled' => '0',
                'message' => '',
                'modules' => [],
            ])
            ->assertRedirect();

        $this->assertFalse(app(MaintenanceModeService::class)->enabled());
        $this->get(route('login'))->assertOk();
    }
}
