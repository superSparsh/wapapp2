<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Campaigns\Services\CampaignSendService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignCompletionStatusTest extends TestCase
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

    public function test_all_failed_recipients_still_marks_campaign_completed(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Sending,
            'total_recipients' => 2,
            'total_failed' => 2,
        ]);

        CampaignRecipient::factory()->for($campaign)->failed()->count(2)->create();

        app(CampaignSendService::class)->refreshCampaignCompletion($campaign);

        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->completed_at);
    }

    public function test_mixed_sent_and_failed_marks_campaign_completed(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Sending,
            'total_recipients' => 2,
            'total_failed' => 1,
        ]);

        CampaignRecipient::factory()->for($campaign)->sent()->create();
        CampaignRecipient::factory()->for($campaign)->failed()->create();

        app(CampaignSendService::class)->refreshCampaignCompletion($campaign);

        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
    }

    public function test_reconcile_moves_stuck_sending_campaign_to_completed(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Sending,
            'total_recipients' => 1,
            'total_failed' => 1,
        ]);

        CampaignRecipient::factory()->for($campaign)->create([
            'status' => CampaignRecipientStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => 'provider error',
        ]);

        $resolved = app(CampaignSendService::class)->reconcileStuckSendingCampaigns();

        $this->assertSame(1, $resolved);
        $this->assertSame(CampaignStatus::Completed, $campaign->fresh()->status);
    }

    public function test_pending_recipients_keep_sending_status(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Sending,
            'total_recipients' => 2,
        ]);

        CampaignRecipient::factory()->for($campaign)->failed()->create();
        CampaignRecipient::factory()->for($campaign)->pending()->create();

        app(CampaignSendService::class)->refreshCampaignCompletion($campaign);

        $this->assertSame(CampaignStatus::Sending, $campaign->fresh()->status);
    }

    public function test_technical_error_marks_campaign_failed(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Sending,
            'total_recipients' => 1,
        ]);

        app(CampaignSendService::class)->markCampaignFailed($campaign, 'Queue dispatch exploded');

        $this->assertSame(CampaignStatus::Failed, $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->completed_at);
    }
}
