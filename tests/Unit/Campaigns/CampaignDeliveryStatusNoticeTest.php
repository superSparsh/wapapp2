<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Campaigns\Support\CampaignDeliveryStatusNotice;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignDeliveryStatusNoticeTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CampaignDeliveryStatusNotice $notice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        tenancy()->initialize($this->testTenant);
        $this->notice = app(CampaignDeliveryStatusNotice::class);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_hidden_for_draft_campaign(): void
    {
        $campaign = Campaign::factory()->create(['status' => CampaignStatus::Draft]);

        $this->assertFalse($this->notice->shouldShow($campaign, ['pending' => 5, 'sent' => 10]));
    }

    public function test_shown_while_recipients_await_delivery_confirmation(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Completed,
            'started_at' => now()->subHours(2),
        ]);
        CampaignRecipient::factory()->for($campaign)->sent()->create();

        $this->assertTrue($this->notice->shouldShow($campaign, [
            'pending' => 0,
            'sent' => 12,
            'delivered' => 80,
            'failed' => 8,
            'total' => 100,
        ]));
    }

    public function test_hidden_when_only_delivered_and_failed_remain(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Completed,
            'started_at' => now()->subHours(2),
        ]);
        CampaignRecipient::factory()->for($campaign)->delivered()->create();
        CampaignRecipient::factory()->for($campaign)->failed()->create();

        $this->assertFalse($this->notice->shouldShow($campaign, [
            'pending' => 0,
            'sent' => 0,
            'delivered' => 92,
            'failed' => 8,
            'total' => 100,
        ]));
    }

    public function test_auto_hides_after_twenty_four_hours(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Completed,
            'started_at' => now()->subHours(25),
        ]);
        CampaignRecipient::factory()->for($campaign)->sent()->count(2)->create();

        $this->assertFalse($this->notice->shouldShow($campaign, [
            'pending' => 0,
            'sent' => 50,
            'total' => 100,
        ]));
    }

    public function test_hidden_when_no_recipient_rows_even_if_aggregate_metrics_suggest_pending(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Completed,
            'started_at' => now()->subHours(1),
            'total_recipients' => 5000,
        ]);

        $this->assertFalse($this->notice->shouldShow($campaign, [
            'pending' => 5000,
            'sent' => 0,
            'total' => 5000,
        ]));
    }

    public function test_hidden_for_sending_campaign_with_no_awaiting_recipients(): void
    {
        $campaign = Campaign::factory()->sending()->create([
            'started_at' => now()->subMinutes(30),
        ]);
        CampaignRecipient::factory()->for($campaign)->delivered()->count(3)->create();

        $this->assertFalse($this->notice->shouldShow($campaign, [
            'pending' => 0,
            'sent' => 0,
            'delivered' => 3,
            'total' => 3,
        ]));
    }
}
