<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatAction;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use App\Models\DripCampaignState;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripAudienceTest extends TestCase
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

    public function test_audience_page_renders_contacts(): void
    {
        $campaign = DripCampaign::factory()->create();

        // Create stats for different contacts
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900001')->count(3)->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900002')->count(2)->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.audience', $campaign))
            ->assertOk()
            ->assertViewIs('automation.drip-audience')
            ->assertViewHas('contacts')
            ->assertViewHas('statsGrid')
            ->assertViewHas('totalContacts', 2);
    }

    public function test_audience_search_filters_contacts(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900001')->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('918888800002')->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.audience', ['campaign' => $campaign, 'search' => '9199999']))
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->total() === 1;
            });
    }

    public function test_audience_stats_grid_counts(): void
    {
        $campaign = DripCampaign::factory()->create();
        $conversation = Conversation::factory()->create();

        // Create states with different statuses
        DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => 'node_1',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addHour(),
        ]);
        DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => 'node_2',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Completed,
            'expires_at' => now()->addHour(),
        ]);
        DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => 'node_3',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAsTenantUser()
            ->get(route('automation.drip.audience', $campaign))
            ->assertOk()
            ->assertViewHas('statsGrid', function ($grid) {
                return $grid['in_action'] === 2  // active + waiting
                    && $grid['done'] === 1;       // completed
            });
    }

    public function test_timeline_page_renders_activity_feed(): void
    {
        $campaign = DripCampaign::factory()->create();
        DripCampaignStat::factory()->count(5)->for($campaign, 'dripCampaign')->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.audience-empty', $campaign))
            ->assertOk()
            ->assertViewIs('automation.drip-audience-empty')
            ->assertViewHas('activities', function ($activities) {
                return $activities->total() === 5;
            });
    }

    public function test_timeline_empty_state(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.audience-empty', $campaign))
            ->assertOk()
            ->assertViewHas('activities', function ($activities) {
                return $activities->total() === 0;
            });
    }

    public function test_trigger_initiates_for_contact(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('automation.drip.audience.trigger', $campaign), [
                'phone' => '919999900001',
            ])
            ->assertRedirect(route('automation.drip.audience', $campaign));
    }

    public function test_trigger_requires_phone(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->post(route('automation.drip.audience.trigger', $campaign), [])
            ->assertSessionHasErrors('phone');
    }
}
