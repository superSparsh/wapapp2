<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Billing\Services\WalletService;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Currency;
use App\Models\CustomerOnboardingSubmission;
use App\Models\CustomerReadinessSubmission;
use App\Models\FormTemplate;
use App\Models\Language;
use App\Models\PageLayout;
use App\Models\Plan;
use App\Models\PlatformTemplate;
use App\Models\Plugin;
use App\Models\RechargeSubscriptionRequest;
use App\Models\RenewSubscriptionRequest;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminExtendedModulesTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Extended Admin',
            'email' => 'admin-extended@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_all_extended_index_routes_render(): void
    {
        $routes = [
            'admin.renew-requests.index',
            'admin.recharge-requests.index',
            'admin.submissions.index',
            'admin.zoho-credits.index',
            'admin.currencies.index',
            'admin.tax.edit',
            'admin.invoice-template.edit',
            'admin.invoice-template.preview',
            'admin.payment-gateways.edit',
            'admin.admin-roles.index',
            'admin.oauth.edit',
            'admin.platform-templates.index',
            'admin.form-templates.index',
            'admin.page-layouts.index',
            'admin.languages.index',
            'admin.plugins.index',
            'admin.admins.index',
        ];

        foreach ($routes as $route) {
            $this->actingAs($this->admin, 'admin')
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_create_and_edit_forms_render(): void
    {
        $currency = Currency::query()->create(['name' => 'US Dollar', 'code' => 'USD', 'format' => '${PRICE}', 'is_active' => true]);
        $role = AdminRole::query()->create(['name' => 'Ops', 'slug' => 'ops', 'permissions' => ['customers'], 'is_active' => true]);
        $language = Language::query()->create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        $template = PlatformTemplate::query()->create(['name' => 'Welcome', 'type' => 'whatsapp', 'is_active' => true]);
        $formTemplate = FormTemplate::query()->create(['name' => 'Contact', 'slug' => 'contact', 'is_active' => true]);
        $pageLayout = PageLayout::query()->create(['name' => 'Privacy', 'slug' => 'privacy', 'is_active' => true]);

        $pages = [
            route('admin.currencies.create'),
            route('admin.currencies.edit', $currency),
            route('admin.admin-roles.create'),
            route('admin.admin-roles.edit', $role),
            route('admin.languages.create'),
            route('admin.languages.edit', $language),
            route('admin.platform-templates.create'),
            route('admin.platform-templates.edit', $template),
            route('admin.form-templates.create'),
            route('admin.form-templates.edit', $formTemplate),
            route('admin.page-layouts.create'),
            route('admin.page-layouts.edit', $pageLayout),
            route('admin.admins.create'),
            route('admin.admins.edit', $this->admin),
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->admin, 'admin')->get($page)->assertOk();
        }
    }

    public function test_renew_request_approval_assigns_plan_and_marks_approved(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Renewal Plan',
            'slug' => 'renewal-plan-'.uniqid(),
            'price' => 1499,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $request = RenewSubscriptionRequest::query()->create([
            'tenant_id' => $this->testTenant->id,
            'plan_id' => $plan->id,
            'status' => RenewSubscriptionRequest::STATUS_PENDING,
            'notes' => 'Please renew for another month.',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.renew-requests.approve', $request))
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(RenewSubscriptionRequest::STATUS_APPROVED, $request->status);
        $this->assertSame((int) $this->admin->id, (int) $request->approved_by);
        $this->assertNotNull($request->approved_at);
        $this->assertSame((int) $plan->id, (int) $this->testTenant->fresh()->plan_id);
    }

    public function test_recharge_request_approval_credits_tenant_wallet(): void
    {
        $request = RechargeSubscriptionRequest::query()->create([
            'tenant_id' => $this->testTenant->id,
            'amount' => 750,
            'currency' => 'INR',
            'status' => RechargeSubscriptionRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.recharge-requests.approve', $request))
            ->assertRedirect();

        $request->refresh();
        $this->assertSame(RechargeSubscriptionRequest::STATUS_APPROVED, $request->status);

        tenancy()->initialize($this->testTenant);
        $balance = app(WalletService::class)->balance();
        tenancy()->end();

        $this->assertSame(750.0, $balance);
    }

    public function test_currency_store_creates_row(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.currencies.store'), [
                'name' => 'Indian Rupee',
                'code' => 'inr',
                'format' => '₹{PRICE}',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.currencies.index'));

        $this->assertDatabaseHas('currencies', [
            'code' => 'INR',
            'name' => 'Indian Rupee',
        ], config('tenancy.database.central_connection'));
    }

    public function test_admin_role_store_and_admin_assignment(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.admin-roles.store'), [
                'name' => 'Support Lead',
                'permissions' => ['customers', 'billing'],
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.admin-roles.index'));

        $role = AdminRole::query()->where('slug', 'support-lead')->firstOrFail();
        $this->assertSame(['customers', 'billing'], $role->permissions);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.admins.store'), [
                'name' => 'Desk Admin',
                'email' => 'desk-admin@wapapp.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'admin_role' => $role->uuid,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.admins.index'));

        $created = Admin::query()->where('email', 'desk-admin@wapapp.test')->firstOrFail();
        $this->assertSame((int) $role->id, (int) $created->admin_role_id);
    }

    public function test_public_readiness_form_submits_and_admin_sees_submission(): void
    {
        $this->get(route('customer-readiness.create'))->assertOk();

        $this->post(route('customer-readiness.store'), [
            'name' => 'Riya Sharma',
            'email' => 'riya@example.com',
            'business_name' => 'Sharma Traders',
            'business_email' => 'billing@sharma.test',
            'website' => 'https://sharma.test',
            'doc_type' => 'gst_certificate',
        ])->assertRedirect(route('customer-readiness.thanks'));

        $submission = CustomerReadinessSubmission::query()->firstOrFail();
        $this->assertSame('Sharma Traders', $submission->business_name);
        $this->assertSame('riya@example.com', $submission->customer_email);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.submissions.index'))
            ->assertOk()
            ->assertSee('Sharma Traders');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.submissions.readiness.show', $submission))
            ->assertOk()
            ->assertSee('riya@example.com');
    }

    public function test_onboarding_submission_tab_and_zoho_credit_detail(): void
    {
        $onboarding = CustomerOnboardingSubmission::query()->create([
            'reference' => 'ONB-1001',
            'company_name' => 'Nimbus Retail',
            'email' => 'ops@nimbus.test',
            'service_label' => 'WhatsApp onboarding',
            'status' => 'pending',
            'payload' => ['gst' => '29ABCDE1234F1Z5'],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.submissions.index', ['tab' => 'onboarding']))
            ->assertOk()
            ->assertSee('Nimbus Retail');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.submissions.onboarding.resend', $onboarding))
            ->assertRedirect();

        $credit = ZohoWalletCreditRequest::query()->create([
            'tenant_id' => $this->testTenant->id,
            'source' => 'zoho',
            'amount' => 2500,
            'currency' => 'INR',
            'status' => 'credited',
            'invoice_number' => 'INV-778',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.zoho-credits.index', ['source' => 'zoho']))
            ->assertOk()
            ->assertSee('INV-778');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.zoho-credits.show', $credit))
            ->assertOk()
            ->assertSee('INV-778');
    }

    public function test_plugin_registration_and_toggle(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.plugins.store'), [
                'title' => 'Zoho Books',
                'type' => 'billing',
                'version' => '1.0.0',
            ])
            ->assertRedirect(route('admin.plugins.index'));

        $plugin = Plugin::query()->where('name', 'zoho-books')->firstOrFail();
        $this->assertFalse($plugin->is_enabled);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.plugins.toggle', $plugin))
            ->assertRedirect();

        $this->assertTrue($plugin->fresh()->is_enabled);
    }

    public function test_tax_and_gateway_settings_persist(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.tax.update'), [
                'tax_enabled' => '1',
                'tax_default_rate' => 18,
                'tax_countries' => '{"IN":18}',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'tax.default_rate',
            'value' => '18',
        ], config('tenancy.database.central_connection'));

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.payment-gateways.update'), [
                'payment_razorpay_enabled' => '1',
                'payment_razorpay_key' => 'rzp_test_key',
                'payment_razorpay_secret' => 'rzp_test_secret',
                'payment_razorpay_webhook_secret' => 'hook_secret',
                'payment_offline_instructions' => 'Wire transfer to HDFC 1234.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'payment.razorpay_key',
            'value' => 'rzp_test_key',
        ], config('tenancy.database.central_connection'));
    }

    public function test_language_and_content_module_crud(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.languages.store'), [
                'name' => 'Hindi',
                'code' => 'HI',
                'region_code' => 'IN',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.languages.index'));

        $this->assertDatabaseHas('languages', [
            'code' => 'hi',
        ], config('tenancy.database.central_connection'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.platform-templates.store'), [
                'name' => 'Order shipped',
                'category' => 'utility',
                'type' => 'whatsapp',
                'body' => 'Your order {{1}} has shipped.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.platform-templates.index'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.form-templates.store'), [
                'name' => 'Lead capture',
                'html' => '<form></form>',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.form-templates.index'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.page-layouts.store'), [
                'name' => 'Terms of service',
                'alias' => 'terms',
                'html' => '<p>Terms</p>',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.page-layouts.index'));

        $this->assertDatabaseHas('platform_templates', ['name' => 'Order shipped'], config('tenancy.database.central_connection'));
        $this->assertDatabaseHas('form_templates', ['slug' => 'lead-capture'], config('tenancy.database.central_connection'));
        $this->assertDatabaseHas('page_layouts', ['slug' => 'terms-of-service'], config('tenancy.database.central_connection'));
    }
}
