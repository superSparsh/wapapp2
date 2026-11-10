<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Alerts\Services\OperationalWhatsAppSender;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Mockery;
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
            ->assertSee($this->testTenant->name)
            ->assertSee('Showing below')
            ->assertSee(route('admin.retention.index', ['window' => 'expired'], false), false);

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

    public function test_retention_can_send_whatsapp_and_email_reminders(): void
    {
        Notification::fake();
        config([
            'operational-alerts.admin_emails' => ['ops@wapapp.test'],
            'operational-alerts.admin_whatsapp_numbers' => ['919999999999'],
            'operational-alerts.plan_expiration.whatsapp_renewal_template' => 'plan_renewal_reminder_wapapp',
            'operational-alerts.plan_expiration.whatsapp_template' => 'plan_expiration_notification_wapapp',
        ]);

        $whatsApp = Mockery::mock(OperationalWhatsAppSender::class);
        $whatsApp->shouldReceive('sendTemplateResult')
            ->twice()
            ->withArgs(function (string $to, string $template, array $params): bool {
                return $template === 'plan_renewal_reminder_wapapp'
                    && isset($params['full_name'], $params['plan_name'], $params['expiration_date'])
                    && ! isset($params['customer_name'], $params['ends_at'], $params['days_left']);
            })
            ->andReturn(['ok' => true, 'error' => null, 'message_id' => null]);
        $this->app->instance(OperationalWhatsAppSender::class, $whatsApp);

        $this->testTenant->forceFill([
            'email' => 'customer@example.test',
            'phone' => '918888888888',
            'settings' => [
                'valid_until' => now()->addDays(10)->toDateString(),
            ],
        ])->save();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.retention.whatsapp', $this->testTenant))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.retention.email', $this->testTenant))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->testTenant->refresh();
        $notes = $this->testTenant->settings['retention_notes'] ?? [];
        $this->assertGreaterThanOrEqual(2, count($notes));
        $types = array_column($notes, 'action_type');
        $this->assertContains('whatsapp_sent', $types);
        $this->assertContains('email_sent', $types);
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
            ->assertRedirect(route('admin.wallet-payments.index', [
                'tenant' => $this->testTenant->id,
                'tab' => 'credits',
            ]));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.wallet-payments.index', [
                'tenant' => $this->testTenant->id,
                'tab' => 'credits',
            ]))
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
                'wallet_balance_unit' => 'usd',
                'wallet_conversion_price' => '84.5',
                'wallet_display_currency_default' => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'general.app_name',
            'value' => 'WapApp Admin',
        ], config('tenancy.database.central_connection'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'wallet.balance_unit',
            'value' => 'usd',
        ], config('tenancy.database.central_connection'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'wallet.conversion_price',
            'value' => '84.5',
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
                'dial_code' => '+91',
                'marketing_price' => 0.8,
                'utility_price' => 0.4,
                'auth_price' => 0.3,
                'service_price' => 0.2,
                'currency' => '₹',
                'status' => '1',
            ])
            ->assertRedirect(route('admin.pricing.index'));

        $this->assertDatabaseHas('country_pricing', [
            'country_code' => 'IN',
            'country_name' => 'India',
            'status' => 1,
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

        $conversation = \App\Models\Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'line_phone' => $this->testLine->phone,
        ]);

        \App\Models\Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'status' => \App\Enums\MessageStatus::Delivered,
            'created_at' => now()->subDays(2),
        ]);
        \App\Models\Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'status' => \App\Enums\MessageStatus::Read,
            'created_at' => now()->subDays(1),
        ]);
        \App\Models\Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'status' => \App\Enums\MessageStatus::Failed,
            'created_at' => now()->subDays(1),
        ]);
        // Outside window — must not count in default 30d? Wait, 40 days ago excluded from 30d.
        \App\Models\Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'status' => \App\Enums\MessageStatus::Failed,
            'created_at' => now()->subDays(40),
        ]);
        // Inbound — excluded
        \App\Models\Message::factory()->create([
            'conversation_id' => $conversation->id,
            'status' => \App\Enums\MessageStatus::Read,
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.whatsapp-health.index', [
                'tenant' => $this->testTenant->id,
                'days' => 30,
            ]))
            ->assertOk()
            ->assertSee('919999999999')
            ->assertSee('GREEN')
            ->assertSee('outbound messages in the selected window', false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.message-performance.index', [
                'tenant' => $this->testTenant->id,
                'days' => 30,
            ]))
            ->assertOk()
            ->assertSee('919999999999')
            ->assertSee('Outbound WhatsApp delivery and read rates', false)
            ->assertSee('Last 30 days')
            ->assertSee('66.7%') // delivery: (1 delivered + 1 read) / 3 sent
            ->assertSee('33.3%'); // read: 1 / 3
    }

    public function test_customer_inbox_masking_and_wallet_display_currency(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.show', $this->testTenant))
            ->assertOk()
            ->assertSee('Inbox settings')
            ->assertSee('Wallet display currency');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.customers.settings', $this->testTenant), [
                'inbox_phone_masking_enabled' => '1',
            ])
            ->assertRedirect();

        $this->testTenant->refresh();
        $this->assertTrue((bool) ($this->testTenant->settings['inbox_phone_masking_enabled'] ?? false));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.customers.wallet-display-currency', $this->testTenant), [
                'currency' => 'USD',
            ])
            ->assertRedirect();

        $this->testTenant->refresh();
        $this->assertSame('USD', $this->testTenant->settings['wallet_display_currency'] ?? null);
    }

    public function test_faq_admin_and_pricing_logs_pages(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.faqs.store'), [
                'heading' => 'How do wallets work?',
                'description' => 'Wallet credits fund WhatsApp conversations.',
                'sort_order' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.faqs.index'));

        $this->assertDatabaseHas('faqs', [
            'heading' => 'How do wallets work?',
            'is_active' => 1,
        ], config('tenancy.database.central_connection'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.faqs.index'))
            ->assertOk()
            ->assertSee('How do wallets work?')
            ->assertSee('Import from legacy');

        $this->mock(\App\Domains\HelpCenter\Services\HelpCenterLegacyImportService::class, function ($mock): void {
            $mock->shouldReceive('import')
                ->once()
                ->withArgs(function (bool $fresh, bool $importFaqs, bool $importTutorials, bool $copyVideos, bool $dryRun): bool {
                    return $fresh === false
                        && $importFaqs === true
                        && $importTutorials === false
                        && $copyVideos === false
                        && $dryRun === false;
                })
                ->andReturn([
                    'faqs' => 9,
                    'tutorials' => 0,
                    'videos_copied' => 0,
                ]);
        });

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.faqs.import-legacy'), ['fresh' => '0'])
            ->assertRedirect(route('admin.faqs.index'))
            ->assertSessionHas('status', 'Imported 9 FAQ(s) from legacy.');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.pricing.logs'))
            ->assertOk()
            ->assertSee('Pricing change logs');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.pricing.index'))
            ->assertOk()
            ->assertSee('Pricing change logs');
    }

    public function test_tutorial_admin_pages_and_legacy_import(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.tutorials.store'), [
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
                'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
                'description' => 'Learn the dashboard.',
                'duration' => '2:00',
                'sort_order' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.tutorials.index'));

        $this->assertDatabaseHas('tutorial_videos', [
            'title' => 'Dashboard Overview',
            'module_name' => 'Module 1: Dashboard',
            'is_active' => 1,
        ], config('tenancy.database.central_connection'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tutorials.index'))
            ->assertOk()
            ->assertSee('Dashboard Overview')
            ->assertSee('Import from legacy');

        $this->mock(\App\Domains\HelpCenter\Services\HelpCenterLegacyImportService::class, function ($mock): void {
            $mock->shouldReceive('import')
                ->once()
                ->withArgs(function (bool $fresh, bool $importFaqs, bool $importTutorials, bool $copyVideos, bool $dryRun): bool {
                    return $fresh === false
                        && $importFaqs === false
                        && $importTutorials === true
                        && $copyVideos === false
                        && $dryRun === false;
                })
                ->andReturn([
                    'faqs' => 0,
                    'tutorials' => 121,
                    'videos_copied' => 0,
                ]);
        });

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.tutorials.import-legacy'), ['fresh' => '0', 'copy_videos' => '0'])
            ->assertRedirect(route('admin.tutorials.index'))
            ->assertSessionHas('status', 'Imported 121 tutorial(s) from legacy.');
    }
}
