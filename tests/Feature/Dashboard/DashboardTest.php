<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domains\Account\Services\NotificationService;
use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Billing\Services\WalletService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WalletTransactionType;
use App\Models\AccountPreference;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_renders_with_credits_period_filter(): void
    {
        $template = Template::factory()->create(['category' => 'MARKETING']);
        $campaign = Campaign::factory()->create([
            'template_id' => $template->id,
            'status' => CampaignStatus::Completed,
            'started_at' => now(),
        ]);

        CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => CampaignRecipientStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard', ['credits_period' => 'daily']))
            ->assertOk()
            ->assertSee('Credits Used')
            ->assertSee('Sort by : Daily');
    }

    public function test_daily_credits_exclude_failed_and_match_legacy_sent_formula(): void
    {
        $marketing = Template::factory()->create(['category' => 'MARKETING']);
        $utility = Template::factory()->create(['category' => 'UTILITY']);

        $marketingCampaign = Campaign::factory()->create([
            'template_id' => $marketing->id,
            'status' => CampaignStatus::Completed,
            'started_at' => now(),
        ]);
        $utilityCampaign = Campaign::factory()->create([
            'template_id' => $utility->id,
            'status' => CampaignStatus::Completed,
            'started_at' => now(),
        ]);

        CampaignRecipient::factory()->count(3)->create([
            'campaign_id' => $marketingCampaign->id,
            'status' => CampaignRecipientStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
        CampaignRecipient::factory()->count(2)->create([
            'campaign_id' => $utilityCampaign->id,
            'status' => CampaignRecipientStatus::Sent,
            'sent_at' => now(),
        ]);
        CampaignRecipient::factory()->create([
            'campaign_id' => $marketingCampaign->id,
            'status' => CampaignRecipientStatus::Failed,
            'sent_at' => now(),
        ]);
        CampaignRecipient::factory()->create([
            'campaign_id' => $marketingCampaign->id,
            'status' => CampaignRecipientStatus::Pending,
            'sent_at' => null,
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('dashboard.credits', ['period' => 'daily']))
            ->assertOk()
            ->assertJsonPath('credits.marketing', 3)
            ->assertJsonPath('credits.utility', 2)
            ->assertJsonPath('credits.sent', 5);
    }

    public function test_dashboard_shows_plan_from_subscription_when_tenant_plan_missing(): void
    {
        $plan = tenancy()->central(fn () => Plan::query()->create([
            'name' => 'Growth Plan',
            'slug' => 'growth-plan-'.uniqid(),
            'price' => 999,
            'currency' => 'INR',
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]));

        $this->testTenant->forceFill(['plan_id' => null])->save();

        Subscription::query()->create([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'amount' => 999,
            'currency' => 'INR',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You are subscribed to Growth Plan')
            ->assertDontSee('No subscription plan selected');
    }

    public function test_campaign_select_shows_recipient_logs_for_selected_campaign(): void
    {
        $campaignA = Campaign::factory()->create(['name' => 'Alpha Campaign']);
        $campaignB = Campaign::factory()->create(['name' => 'Beta Campaign']);

        $contact = Contact::factory()->create(['name' => 'Ravi Kumar', 'status' => ContactStatus::Subscribed]);

        CampaignRecipient::factory()->create([
            'campaign_id' => $campaignB->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'status' => CampaignRecipientStatus::Sent,
            'sent_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard', ['campaign_id' => $campaignB->uuid]))
            ->assertOk()
            ->assertSee('Alpha Campaign')
            ->assertSee('Beta Campaign')
            ->assertSee('Ravi Kumar');
    }

    public function test_wallet_history_labels_withdrawal_and_accepts_period_filter(): void
    {
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Debit,
            'amount' => 12.5,
            'currency' => 'INR',
            'balance_after' => 100,
            'description' => 'Campaign send deduction',
            'created_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard.wallet', ['period' => 'all']))
            ->assertOk()
            ->assertSee('Withdrawal')
            ->assertSee('Campaign send deduction')
            ->assertSee('Sort by : All time');
    }

    public function test_wallet_history_balance_after_walks_back_from_live_balance(): void
    {
        app(WalletService::class)->account()->update(['balance' => 400]);

        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Credit,
            'amount' => 500,
            'currency' => 'INR',
            'balance_after' => 12.34,
            'description' => 'Wallet top up',
            'created_at' => now()->subHour(),
        ]);
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Debit,
            'amount' => 100,
            'currency' => 'INR',
            'balance_after' => -999,
            'description' => 'Campaign send deduction',
            'created_at' => now(),
        ]);

        $rows = app(WalletService::class)
            ->paginateTransactions(period: 'all')
            ->getCollection();

        $this->assertCount(2, $rows);
        $this->assertSame('Campaign send deduction', $rows[0]->description);
        $this->assertSame(400.0, (float) $rows[0]->display_balance_after);
        $this->assertSame('Wallet top up', $rows[1]->description);
        $this->assertSame(500.0, (float) $rows[1]->display_balance_after);

        $this->actingAsTenantUser()
            ->get(route('dashboard.wallet', ['period' => 'all']))
            ->assertOk()
            ->assertSee('₹ 400.00')
            ->assertSee('₹ 500.00')
            ->assertDontSee('₹ 999.00')
            ->assertDontSee('₹ 12.34');
    }

    public function test_wallet_history_balance_after_stays_correct_on_page_two(): void
    {
        config(['billing.wallet.history_per_page' => 1]);
        app(WalletService::class)->account()->update(['balance' => 400]);

        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Credit,
            'amount' => 500,
            'currency' => 'INR',
            'balance_after' => 0,
            'description' => 'Wallet top up',
            'created_at' => now()->subHour(),
        ]);
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Debit,
            'amount' => 100,
            'currency' => 'INR',
            'balance_after' => 0,
            'description' => 'Campaign send deduction',
            'created_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('dashboard.wallet', ['period' => 'all', 'page' => 2]))
            ->assertOk()
            ->assertSee('Wallet top up')
            ->assertSee('₹ 500.00')
            ->assertDontSee('Campaign send deduction');
    }

    public function test_wallet_history_filters_by_custom_date_range(): void
    {
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Credit,
            'amount' => 100,
            'currency' => 'INR',
            'balance_after' => 100,
            'description' => 'Inside range topup',
            'created_at' => now()->subDays(2),
        ]);
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Debit,
            'amount' => 50,
            'currency' => 'INR',
            'balance_after' => 50,
            'description' => 'Outside range debit',
            'created_at' => now()->subDays(40),
        ]);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $this->actingAsTenantUser()
            ->get(route('dashboard.wallet', ['from' => $from, 'to' => $to]))
            ->assertOk()
            ->assertSee('Inside range topup')
            ->assertDontSee('Outside range debit')
            ->assertSee('Export CSV');
    }

    public function test_wallet_history_export_downloads_csv(): void
    {
        WalletTransaction::query()->create([
            'type' => WalletTransactionType::Credit,
            'amount' => 250,
            'currency' => 'INR',
            'balance_after' => 250,
            'description' => 'Exportable credit',
            'created_at' => now(),
        ]);

        $response = $this->actingAsTenantUser()
            ->get(route('dashboard.wallet.export', ['period' => 'all']));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('Exportable credit', $csv);
        $this->assertStringContainsString('Description', $csv);
    }

    public function test_mark_all_notifications_read_endpoint(): void
    {
        $this->actingAsTenantUser()
            ->postJson(route('notifications.read'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_bell_notifications_only_include_unread_after_mark_read(): void
    {
        ActivityLog::query()->create([
            'uid' => (string) Str::uuid(),
            'scope' => 'tenant',
            'actor_type' => 'user',
            'action' => 'campaign.created',
            'description' => 'Old notification',
            'created_at' => now()->subMinute(),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('notifications.read'))
            ->assertOk();

        ActivityLog::query()->create([
            'uid' => (string) Str::uuid(),
            'scope' => 'tenant',
            'actor_type' => 'user',
            'action' => 'campaign.created',
            'description' => 'Brand new notification',
            'created_at' => now()->addSecond(),
        ]);

        $service = app(NotificationService::class);

        $this->assertSame(1, $service->unreadCount());
        $this->assertCount(1, $service->recent());
        $this->assertSame('Brand new notification', $service->recent()->first()->description);
    }

    public function test_notification_read_state_survives_session_flush_like_login(): void
    {
        ActivityLog::query()->create([
            'uid' => (string) Str::uuid(),
            'scope' => 'tenant',
            'actor_type' => 'user',
            'action' => 'campaign.created',
            'description' => 'Already read notification',
            'created_at' => now()->subMinutes(5),
        ]);

        $this->actingAsTenantUser()
            ->postJson(route('notifications.read'))
            ->assertOk();

        // Login regenerates session — previously this wiped notifications.read_at.
        session()->flush();
        $this->actingAsTenantUser();

        $service = app(NotificationService::class);

        $this->assertSame(0, $service->unreadCount());
        $this->assertCount(0, $service->recent());
        $this->assertNotNull(AccountPreference::current()->notifications_read_at);
    }

    public function test_credits_endpoint_returns_period_payload(): void
    {
        $this->actingAsTenantUser()
            ->getJson(route('dashboard.credits', ['period' => 'weekly']))
            ->assertOk()
            ->assertJsonPath('period', 'weekly')
            ->assertJsonStructure(['credits' => ['sent', 'marketing', 'utility', 'service']]);
    }

    public function test_campaign_review_endpoint_returns_recipients(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Live Campaign']);
        $contact = Contact::factory()->create(['name' => 'Neha']);

        CampaignRecipient::factory()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'status' => CampaignRecipientStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('dashboard.campaign-review', ['campaign_id' => $campaign->uuid]))
            ->assertOk()
            ->assertJsonPath('campaign.name', 'Live Campaign')
            ->assertJsonPath('recipients.0.name', 'Neha')
            ->assertJsonPath('campaign.stats_url', route('campaigns.statistics', $campaign));
    }
}
