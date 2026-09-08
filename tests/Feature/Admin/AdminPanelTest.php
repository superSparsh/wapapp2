<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Enums\TenantStatus;
use App\Enums\TenantUserAccountType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_login_and_see_dashboard(): void
    {
        $this->post(route('admin.login.store'), [
            'email' => 'admin@wapapp.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Platform overview');
    }

    public function test_admin_can_list_and_view_customers(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee($this->testTenant->name);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.show', $this->testTenant))
            ->assertOk()
            ->assertSee($this->testTenant->id);
    }

    public function test_admin_can_update_customer_status_and_plan(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 999,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.customers.update', $this->testTenant), [
                'name' => 'Updated Tenant',
                'company_name' => 'Updated Co',
                'email' => 'owner@example.com',
                'phone' => '919999999999',
                'plan_id' => $plan->id,
                'status' => TenantStatus::Suspended->value,
                'timezone' => 'Asia/Kolkata',
            ])
            ->assertRedirect(route('admin.customers.show', $this->testTenant));

        $this->testTenant->refresh();
        $this->assertSame('Updated Tenant', $this->testTenant->name);
        $this->assertSame(TenantStatus::Suspended, $this->testTenant->status);
        $this->assertSame($plan->id, $this->testTenant->plan_id);
    }

    public function test_admin_can_login_as_customer_and_return(): void
    {
        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->testUser->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'phone' => $this->testUser->phone,
            ],
        );

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.customers.login-as', $this->testTenant))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->testUser, 'web');
        $this->assertGuest('admin');

        $this->post(route('admin.impersonation.stop'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin, 'admin');
    }

    public function test_admin_can_manage_plans(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.plans.store'), [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 499,
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'is_active' => 1,
                'sort_order' => 0,
            ])
            ->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('plans', ['slug' => 'starter'], config('tenancy.database.central_connection'));
    }
}
