<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Campaigns\Services\CampaignService;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new CampaignService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_create_creates_draft_campaign(): void
    {
        $campaign = $this->service->create([
            'name' => 'Test Campaign',
        ]);

        $this->assertInstanceOf(Campaign::class, $campaign);
        $this->assertSame('Test Campaign', $campaign->name);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
    }

    public function test_create_with_scheduled_at_sets_scheduled_status(): void
    {
        $campaign = $this->service->create([
            'name' => 'Scheduled Campaign',
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $this->assertSame(CampaignStatus::Scheduled, $campaign->status);
    }

    public function test_update_modifies_draft_campaign(): void
    {
        $campaign = Campaign::factory()->draft()->create(['name' => 'Old Name']);

        $updated = $this->service->update($campaign, ['name' => 'New Name']);

        $this->assertSame('New Name', $updated->name);
    }

    public function test_update_with_scheduled_at_sets_scheduled_status(): void
    {
        $campaign = Campaign::factory()->draft()->create(['name' => 'Later Campaign']);

        $updated = $this->service->update($campaign, [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $this->assertSame(CampaignStatus::Scheduled, $updated->status);
    }

    public function test_schedule_transitions_to_scheduled(): void
    {
        $campaign = Campaign::factory()->draft()->create();

        $result = $this->service->schedule($campaign);

        $this->assertSame(CampaignStatus::Scheduled, $result->status);
    }

    public function test_cancel_transitions_to_cancelled(): void
    {
        $campaign = Campaign::factory()->scheduled()->create();

        $result = $this->service->cancel($campaign);

        $this->assertSame(CampaignStatus::Cancelled, $result->status);
    }

    public function test_toggle_pauses_sending_campaign(): void
    {
        $campaign = Campaign::factory()->sending()->create();

        $result = $this->service->toggle($campaign);

        $this->assertSame(CampaignStatus::Paused, $result->status);
    }

    public function test_toggle_resumes_paused_campaign(): void
    {
        $campaign = Campaign::factory()->paused()->create();

        $result = $this->service->toggle($campaign);

        $this->assertSame(CampaignStatus::Sending, $result->status);
    }

    public function test_delete_removes_campaign_and_recipients(): void
    {
        $campaign = Campaign::factory()->create();
        CampaignRecipient::factory()->count(5)->for($campaign)->create();

        $this->service->delete($campaign);

        $this->assertSoftDeleted('campaigns', ['id' => $campaign->id]);
        $this->assertSame(0, CampaignRecipient::query()->where('campaign_id', $campaign->id)->count());
    }

    public function test_duplicate_creates_copy_with_suffix(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Original']);

        $clone = $this->service->duplicate($campaign);

        $this->assertSame('Original (Copy)', $clone->name);
        $this->assertSame(CampaignStatus::Draft, $clone->status);
        $this->assertNotSame($campaign->id, $clone->id);
    }

    public function test_duplicate_resets_counters(): void
    {
        $campaign = Campaign::factory()->withStats(100, 80, 10, 70)->create();

        $clone = $this->service->duplicate($campaign);

        $this->assertSame(0, $clone->total_recipients);
        $this->assertSame(0, $clone->total_delivered);
        $this->assertSame(0, $clone->total_failed);
        $this->assertSame(0, $clone->total_read);
    }

    public function test_duplicate_preserves_original(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Keep Me']);

        $this->service->duplicate($campaign);

        $this->assertDatabaseHas('campaigns', ['name' => 'Keep Me', 'id' => $campaign->id]);
    }
}
