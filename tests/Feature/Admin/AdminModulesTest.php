<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Announcement;
use App\Models\CloudBillUpload;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminModulesTest extends TestCase
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
            'email' => 'admin-modules@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_retention_lists_by_valid_until_and_persists_note(): void
    {
        $this->testTenant->forceFill([
            'settings' => [
                'valid_until' => now()->addDays(5)->toDateString(),
            ],
        ])->save();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.retention.index', ['window' => '7']))
            ->assertOk()
            ->assertSee($this->testTenant->name);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.retention.notes.store', $this->testTenant), [
                'note' => 'Called about renewal',
                'action_type' => 'called',
            ])
            ->assertRedirect();

        $this->testTenant->refresh();
        $notes = $this->testTenant->settings['retention_notes'] ?? [];
        $this->assertNotEmpty($notes);
        $this->assertSame('Called about renewal', end($notes)['note']);
    }

    public function test_wallet_recharges_and_billing_audit_list_cross_tenant_credits(): void
    {
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Credit,
            'amount' => 500,
            'currency' => 'INR',
            'balance_after' => 500,
            'description' => 'Admin wallet topup',
            'razorpay_payment_id' => 'pay_test_123',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.wallet-recharges.index', ['tenant' => $this->testTenant->id]))
            ->assertOk()
            ->assertSee('Admin wallet topup')
            ->assertSee('pay_test_123');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing-audit.index', ['type' => 'wallet', 'tenant' => $this->testTenant->id]))
            ->assertOk()
            ->assertSee('Admin wallet topup');
    }

    public function test_data_purge_marks_and_unmarks_tenant(): void
    {
        $this->testTenant->forceFill([
            'status' => TenantStatus::Suspended,
            'settings' => ['valid_until' => now()->subDays(10)->toDateString()],
        ])->save();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.data-purge.index'))
            ->assertOk()
            ->assertSee($this->testTenant->name);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.data-purge.mark', $this->testTenant))
            ->assertRedirect();

        $this->testTenant->refresh();
        $this->assertNotEmpty($this->testTenant->settings['purge_requested_at'] ?? null);
        $this->assertSame(TenantStatus::Suspended, $this->testTenant->status);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.data-purge.unmark', $this->testTenant))
            ->assertRedirect();

        $this->testTenant->refresh();
        $this->assertArrayNotHasKey('purge_requested_at', $this->testTenant->settings ?? []);
    }

    public function test_settings_and_announcements_crud(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.settings.update'), [
                'general_app_name' => 'WapApp Admin',
                'general_support_email' => 'support@example.com',
                'mailer_from_address' => 'noreply@example.com',
                'mailer_from_name' => 'WapApp',
                'payment_razorpay_enabled' => '1',
                'payment_primary_gateway' => 'razorpay',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'general.app_name',
            'value' => 'WapApp Admin',
        ], config('tenancy.database.central_connection'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.announcements.store'), [
                'title' => 'Maintenance window',
                'body' => 'We will be down briefly.',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseHas('announcements', [
            'title' => 'Maintenance window',
            'is_active' => 1,
        ], config('tenancy.database.central_connection'));

        $announcement = Announcement::query()->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.announcements.toggle', $announcement))
            ->assertRedirect();

        $this->assertFalse($announcement->fresh()->is_active);
    }

    public function test_country_pricing_and_razorpay_subscriptions(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.pricing.store'), [
                'country_code' => 'IN',
                'country_name' => 'India',
                'marketing_rate' => 0.8,
                'utility_rate' => 0.4,
                'authentication_rate' => 0.3,
                'service_rate' => 0.2,
                'currency' => 'USD',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.pricing.index'));

        $this->assertDatabaseHas('country_pricing', [
            'country_code' => 'IN',
            'country_name' => 'India',
        ], config('tenancy.database.central_connection'));

        $plan = tenancy()->central(fn () => Plan::query()->create([
            'name' => 'Growth',
            'slug' => 'growth-'.uniqid(),
            'price' => 999,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]));

        Subscription::query()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'amount' => 999,
            'currency' => 'INR',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'razorpay_subscription_id' => 'sub_test_abc',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.razorpay.index', ['tenant' => $this->testTenant->id]))
            ->assertOk()
            ->assertSee('sub_test_abc');
    }

    public function test_cloud_bills_upload_list_and_update(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.cloud-bills.store'), [
                'file' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
                'period' => '2026-08',
                'amount' => 120.5,
                'currency' => 'USD',
                'exchange_rate' => 83.1,
            ])
            ->assertRedirect(route('admin.cloud-bills.index'));

        $bill = CloudBillUpload::query()->firstOrFail();
        $this->assertSame('invoice.pdf', $bill->filename);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.cloud-bills.index'))
            ->assertOk()
            ->assertSee('invoice.pdf');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.cloud-bills.update', $bill), [
                'amount' => 130,
                'status' => 'reviewed',
            ])
            ->assertRedirect();

        $this->assertSame('reviewed', $bill->fresh()->status);
    }

    public function test_whatsapp_health_and_message_performance(): void
    {
        $this->testLine->forceFill([
            'quality_rating' => 'GREEN',
            'messaging_limit_tier' => 'TIER_1K',
        ])->save();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.whatsapp-health.index', ['tenant' => $this->testTenant->id]))
            ->assertOk()
            ->assertSee('919999999999')
            ->assertSee('GREEN');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.message-performance.index', ['tenant' => $this->testTenant->id]))
            ->assertOk()
            ->assertSee('919999999999');
    }
}
