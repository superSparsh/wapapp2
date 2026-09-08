<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripInsightsTest extends TestCase
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

    public function test_insights_page_renders_overview_stats(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900001')->entered()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900002')->entered()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900001')->completed()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.insights', $campaign))
            ->assertOk()
            ->assertViewIs('automation.drip-insights')
            ->assertViewHas('overview', function ($overview) {
                return $overview['total_contacts'] === 2
                    && $overview['involved'] === 2;
            });
    }

    public function test_insights_performance_steps_shows_per_node_data(): void
    {
        $campaign = DripCampaign::factory()->create();

        // Node 1: 5 entered, 3 completed
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->count(5)->forNode('node_1', 'welcomeMessage')->entered()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->count(3)->forNode('node_1', 'welcomeMessage')->completed()->create();

        // Node 2: 4 entered, 2 completed
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->count(4)->forNode('node_2', 'templateMessage')->entered()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->count(2)->forNode('node_2', 'templateMessage')->completed()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.insights', $campaign))
            ->assertOk()
            ->assertViewHas('steps', function ($steps) {
                return $steps->count() === 2
                    && $steps->contains(fn ($s) => $s['node_id'] === 'node_1' && $s['triggered'] === 5)
                    && $steps->contains(fn ($s) => $s['node_id'] === 'node_2' && $s['completed'] === 2);
            });
    }

    public function test_insights_empty_campaign_shows_zero_stats(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.insights', $campaign))
            ->assertOk()
            ->assertViewHas('overview', function ($overview) {
                return $overview['total_contacts'] === 0
                    && $overview['involved'] === 0
                    && $overview['completion_pct'] === '0.00%';
            })
            ->assertViewHas('steps', function ($steps) {
                return $steps->isEmpty();
            });
    }
}
