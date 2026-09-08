<?php

declare(strict_types=1);

namespace Tests\Unit\Drip;

use App\Domains\Drip\Services\DripAudiencePresenter;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatAction;
use App\Models\DripCampaign;
use App\Models\DripCampaignStat;
use App\Models\DripCampaignState;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripAudiencePresenterTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private DripAudiencePresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->presenter = new DripAudiencePresenter();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_stats_grid_counts_states_correctly(): void
    {
        $campaign = DripCampaign::factory()->create();
        $conversation = Conversation::factory()->create();

        DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => 'n1',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addHour(),
        ]);
        DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => 'n2',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);
        DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => 'n3',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Completed,
            'expires_at' => now()->addHour(),
        ]);
        DripCampaignState::query()->create([
            'conversation_id' => $conversation->id,
            'drip_campaign_id' => $campaign->id,
            'current_node_id' => 'n4',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);

        $grid = $this->presenter->statsGrid($campaign);

        $this->assertSame(2, $grid['in_action']); // active + waiting
        $this->assertSame(1, $grid['done']);       // completed
        $this->assertSame(2, $grid['pending']);    // waiting + expired
        $this->assertSame(0, $grid['errors']);
    }

    public function test_stats_grid_counts_errors_from_stats(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('911111111111')->error()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('912222222222')->error()->create();
        // Same phone as above, should not double count
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('912222222222')->error()->create();

        $grid = $this->presenter->statsGrid($campaign);

        $this->assertSame(2, $grid['errors']);
    }

    public function test_contacts_groups_by_phone(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('911111111111')->count(3)->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('912222222222')->count(5)->create();

        $result = $this->presenter->contacts($campaign);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['contacts']->items());
    }

    public function test_contacts_search_filters_by_phone(): void
    {
        $campaign = DripCampaign::factory()->create();

        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('911111111111')->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('919999900001')->create();

        $result = $this->presenter->contacts($campaign, search: '9199999');

        $this->assertSame(1, $result['total']);
    }

    public function test_timeline_ordered_by_created_at_desc(): void
    {
        $campaign = DripCampaign::factory()->create();

        $old = DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('910000000001')->create();
        $old->forceFill(['created_at' => now()->subDays(5)])->save();

        $new = DripCampaignStat::factory()->for($campaign, 'dripCampaign')
            ->forPhone('910000000002')->create();

        $result = $this->presenter->timeline($campaign);

        $items = $result->items();
        $this->assertSame('910000000002', $items[0]->contact_phone);
        $this->assertSame('910000000001', $items[1]->contact_phone);
    }

    public function test_timeline_paginates(): void
    {
        $campaign = DripCampaign::factory()->create();
        DripCampaignStat::factory()->for($campaign, 'dripCampaign')->count(20)->create();

        $result = $this->presenter->timeline($campaign, perPage: 5);

        $this->assertSame(20, $result->total());
        $this->assertSame(5, $result->perPage());
        $this->assertCount(5, $result->items());
    }

    public function test_stats_grid_empty_campaign(): void
    {
        $campaign = DripCampaign::factory()->create();

        $grid = $this->presenter->statsGrid($campaign);

        $this->assertSame(0, $grid['in_action']);
        $this->assertSame(0, $grid['done']);
        $this->assertSame(0, $grid['pending']);
        $this->assertSame(0, $grid['errors']);
    }

    public function test_contacts_empty_campaign(): void
    {
        $campaign = DripCampaign::factory()->create();

        $result = $this->presenter->contacts($campaign);

        $this->assertSame(0, $result['total']);
        $this->assertCount(0, $result['contacts']->items());
    }
}
