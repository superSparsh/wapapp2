<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domains\Audience\Enums\ContactStatus;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_mark_all_notifications_read_endpoint(): void
    {
        $this->actingAsTenantUser()
            ->postJson(route('notifications.read'))
            ->assertOk()
            ->assertJson(['success' => true]);
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
