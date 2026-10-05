<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Campaigns\Support\CampaignDeliveryStatusNotice;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
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

        $this->assertFalse($this->notice->shouldShow($campaign, [
            'pending' => 0,
            'sent' => 50,
            'total' => 100,
        ]));
    }
}
