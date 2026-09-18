<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin-maint@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_enable_maintenance_and_customer_login_is_blocked(): void
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

        $this->get(route('login'))
            ->assertStatus(503)
            ->assertSee('Upgrading systems tonight.', false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.maintenance.edit'))
            ->assertOk();
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
