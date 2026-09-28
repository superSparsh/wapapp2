<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantStatus;
use App\Enums\TenantUserAccountType;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\TenantUserAccess;
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

    public function test_admin_header_shows_customer_view_and_my_profile_not_direct_logout(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Customer View')
            ->assertSee('My Profile')
            ->assertSee(route('admin.customer-view'), false)
            ->assertSee(route('admin.account.profile'), false)
            ->assertDontSee('>Logout</button>', false);
    }

    public function test_admin_can_view_and_update_own_profile(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.account.profile'))
            ->assertOk()
            ->assertSee('My Profile');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.account.update'), [
                'name' => 'Updated Admin',
                'email' => 'admin@wapapp.test',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('admin.account.profile'));

        $this->assertSame('Updated Admin', $this->admin->fresh()->name);
    }

    public function test_customer_view_without_linked_account_redirects_to_customers(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customer-view'))
            ->assertRedirect(route('admin.customers.index'));
    }

    public function test_admin_nav_matches_legacy_top_level_groups(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Customer')
            ->assertSee('Plan')
            ->assertSee('Announcement')
            ->assertSee('Submissions')
            ->assertSee('Plugins')
            ->assertDontSee('Plans & Pricing');
    }

    public function test_admin_can_list_and_view_customers(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee($this->testTenant->name)
            ->assertSee('Activity logs', false)
            ->assertSee('Billing audit', false)
            ->assertSee('WhatsApp lines', false)
            ->assertSee('Extend validity', false)
            ->assertSee('Assign plan', false)
            ->assertSee('Disable', false)
            ->assertSee('Delete account data', false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.show', $this->testTenant))
            ->assertOk()
            ->assertSee($this->testTenant->id);
    }

    public function test_admin_can_view_customer_activity_logs(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.activity-logs', $this->testTenant))
            ->assertOk()
            ->assertSee('Activity logs');
    }

    public function test_admin_can_extend_validity_and_credit_wallet_from_listing(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.customers.index'))
            ->post(route('admin.customers.extend-validity', $this->testTenant), [
                'days' => 7,
                'wallet_amount' => 50,
            ])
            ->assertRedirect(route('admin.customers.index'));

        $this->testTenant->refresh();
        $this->assertNotNull(data_get($this->testTenant->settings, 'valid_until'));

        tenancy()->initialize($this->testTenant);
        $balance = (float) \App\Models\WalletAccount::query()->value('balance');
        tenancy()->end();

        $this->assertSame(50.0, $balance);
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
                'plan' => $plan->uuid,
                'status' => TenantStatus::Suspended->value,
                'timezone' => 'Asia/Kolkata',
            ])
            ->assertRedirect(route('admin.customers.show', $this->testTenant));

        $this->testTenant->refresh();
        $this->assertSame('Updated Tenant', $this->testTenant->name);
        $this->assertSame(TenantStatus::Suspended, $this->testTenant->status);
        $this->assertSame($plan->id, $this->testTenant->plan_id);
    }

    public function test_admin_can_wipe_customer_account_data_keeping_profile_and_subscription(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Wipe Keep Plan',
            'slug' => 'wipe-keep-'.uniqid(),
            'price' => 499,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->testTenant->forceFill([
            'plan_id' => $plan->id,
            'email' => 'keep-me@example.com',
            'company_name' => 'Keep Co',
            'settings' => ['valid_until' => now()->addMonth()->toDateString()],
        ])->save();

        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->testUser->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'phone' => $this->testUser->phone,
            ],
        );

        tenancy()->initialize($this->testTenant);

        \App\Models\Subscription::query()->create([
            'plan_id' => $plan->id,
            'status' => \App\Enums\SubscriptionStatus::Active,
            'amount' => 499,
            'currency' => 'INR',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        \App\Models\Contact::query()->create([
            'phone' => '919888877766',
            'name' => 'Wipe Me',
            'opt_in_status' => \App\Enums\ContactOptInStatus::OptedIn,
        ]);

        $ownerId = $this->testUser->id;
        $lineId = $this->testLine->id;
        tenancy()->end();

        $this->assertSame(1, \App\Models\WhatsappLineRegistry::query()
            ->where('tenant_id', $this->testTenant->id)
            ->count());

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.customers.index'))
            ->post(route('admin.customers.wipe-account', $this->testTenant))
            ->assertRedirect(route('admin.customers.index'))
            ->assertSessionHas('status');

        $this->testTenant->refresh();
        $this->assertSame(TenantStatus::Suspended, $this->testTenant->status);
        $this->assertSame('keep-me@example.com', $this->testTenant->email);
        $this->assertSame('Keep Co', $this->testTenant->company_name);
        $this->assertSame($plan->id, $this->testTenant->plan_id);
        $this->assertNotNull(data_get($this->testTenant->settings, 'account_wiped_at'));
        $this->assertNotNull(data_get($this->testTenant->settings, 'valid_until'));

        $this->assertTrue(
            TenantUserAccess::query()
                ->where('tenant_id', $this->testTenant->id)
                ->where('email', strtolower($this->testUser->email))
                ->exists()
        );

        $this->assertSame(0, \App\Models\WhatsappLineRegistry::query()
            ->where('tenant_id', $this->testTenant->id)
            ->count());

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, \App\Models\Subscription::query()->count());
        $this->assertTrue(\App\Models\User::query()->whereKey($ownerId)->exists());
        $this->assertSame(0, \App\Models\Contact::query()->count());
        $this->assertSame(0, \App\Models\WhatsappLine::query()->whereKey($lineId)->count());
        tenancy()->end();
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

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Admin Area')
            ->assertDontSee('Admin view as customer')
            ->assertSee('You are logged in as')
            ->assertSee('Return to admin')
            ->assertSee(route('admin.impersonation.stop'), false);

        $this->post(route('admin.impersonation.stop'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin, 'admin');
    }

    public function test_login_as_survives_stale_customer_password_hash_in_session(): void
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

        // Simulate leftover customer session keys sharing the admin cookie.
        $this->actingAs($this->admin, 'admin')
            ->withSession([
                'auth' => [
                    'tenant_id' => $this->testTenant->id,
                    'guard' => 'web',
                ],
                'password_hash_web' => 'stale-invalid-hash',
            ])
            ->actingAs($this->testUser, 'web')
            ->post(route('admin.customers.login-as', $this->testTenant))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->testUser, 'web');
        $this->assertGuest('admin');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Return to admin');
    }

    public function test_login_as_shows_admin_area_only_when_customer_has_admin_view(): void
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

        config(['admin.view_emails' => [strtolower($this->testUser->email)]]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.customers.login-as', $this->testTenant))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin Area')
            ->assertSee('You are logged in as')
            ->assertSee('Return to admin')
            ->assertSee(route('admin.impersonation.stop'), false);
    }

    public function test_admin_own_customer_does_not_show_view_as_customer_banner(): void
    {
        $this->admin->forceFill(['email' => $this->testUser->email])->save();

        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->testUser->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'phone' => $this->testUser->phone,
            ],
        );

        config(['admin.view_emails' => [strtolower($this->testUser->email)]]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.customers.login-as', $this->testTenant))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin Area')
            ->assertDontSee('Admin view as customer')
            ->assertDontSee('Return to admin');
    }

    public function test_customer_view_into_linked_account_does_not_show_view_as_customer_banner(): void
    {
        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->admin->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'phone' => $this->testUser->phone,
            ],
        );

        config(['admin.view_emails' => [strtolower($this->testUser->email)]]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customer-view'))
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin Area')
            ->assertDontSee('Admin view as customer')
            ->assertDontSee('Return to admin');
    }

    public function test_admin_can_manage_plans(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.plans.store'), [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 499,
                'starting_wallet_balance' => 100,
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'validity_days' => 45,
                'is_active' => 1,
                'sort_order' => 0,
                'features' => [
                    'campaigns' => 1,
                    'inbox' => 1,
                    'drip' => 1,
                    'carousel_templates' => 1,
                    'advance' => 1,
                ],
            ])
            ->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('plans', [
            'slug' => 'starter',
            'starting_wallet_balance' => 100,
            'validity_days' => 45,
        ], config('tenancy.database.central_connection'));

        $plan = Plan::query()->where('slug', 'starter')->firstOrFail();
        $this->assertNotEmpty($plan->uuid);
        $this->assertSame(45, $plan->resolvedValidityDays());
        $this->assertTrue((bool) data_get($plan->features, 'campaigns'));
        $this->assertTrue((bool) data_get($plan->features, 'carousel_templates'));
        $this->assertTrue((bool) data_get($plan->features, 'advance'));
        $this->assertFalse((bool) data_get($plan->features, 'commerce'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.plans.toggle-status', $plan))
            ->assertRedirect();
        $this->assertFalse($plan->fresh()->is_active);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertSee('Activate')
            ->assertSee('Wallet start')
            ->assertSee('Features');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.plans.edit', $plan))
            ->assertOk()
            ->assertSee($plan->name)
            ->assertSee('Starting wallet balance')
            ->assertSee('Validity days')
            ->assertSee('Plan modules / features')
            ->assertSee('Carousel Templates')
            ->assertSee('Apply Advance preset');

        $this->assertStringContainsString($plan->uuid, route('admin.plans.edit', $plan));
        $this->assertStringNotContainsString('/plans/'.$plan->id.'/', route('admin.plans.edit', $plan));
    }

    public function test_tenant_user_menu_shows_admin_view_when_email_matches_admin(): void
    {
        Admin::query()->where('email', $this->admin->email)->delete();
        Admin::query()->create([
            'name' => 'Linked Admin',
            'email' => $this->testUser->email,
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin View')
            ->assertSee('Admin Area')
            ->assertSee(route('admin.enter-from-app'), false);
    }

    public function test_tenant_user_menu_shows_admin_view_for_allowlisted_email(): void
    {
        config(['admin.view_emails' => [strtolower($this->testUser->email)]]);

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Admin View');
    }

    public function test_admin_view_bridge_logs_into_admin_guard(): void
    {
        Admin::query()->where('email', $this->admin->email)->delete();
        $linked = Admin::query()->create([
            'name' => 'Linked Admin',
            'email' => $this->testUser->email,
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAsTenantUser()
            ->get(route('admin.enter-from-app'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($linked->fresh(), 'admin');
        $this->assertAuthenticatedAs($this->testUser, 'web');
    }

    public function test_admin_view_allowlist_provisions_admin_and_skips_login_page(): void
    {
        Admin::query()->where('email', $this->testUser->email)->delete();
        config(['admin.view_emails' => [strtolower($this->testUser->email)]]);

        $this->actingAsTenantUser()
            ->get(route('admin.enter-from-app'))
            ->assertRedirect(route('admin.dashboard'))
            ->assertDontSee('admin/login', false);

        $this->assertAuthenticated('admin');
        $this->assertDatabaseHas('admins', [
            'email' => strtolower($this->testUser->email),
            'is_active' => true,
        ], config('tenancy.database.central_connection'));
    }

    public function test_admin_view_hidden_without_admin_access(): void
    {
        Admin::query()->where('email', $this->testUser->email)->delete();
        config(['admin.view_emails' => []]);

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Admin View<', false)
            ->assertDontSee('Admin Area')
            ->assertDontSee('Admin view as customer');
    }
}
