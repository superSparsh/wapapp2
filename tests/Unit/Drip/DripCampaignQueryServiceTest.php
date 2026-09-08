<?php

declare(strict_types=1);

namespace Tests\Unit\Drip;

use App\Domains\Drip\Services\DripCampaignQueryService;
use App\Enums\ChatbotFlowStatus;
use App\Models\DripCampaign;
use App\Models\ChatbotFlow;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripCampaignQueryServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private DripCampaignQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new DripCampaignQueryService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_paginate_returns_only_drip_type(): void
    {
        DripCampaign::factory()->count(3)->create();
        ChatbotFlow::factory()->count(2)->create();

        $result = $this->service->paginate();

        $this->assertSame(3, $result->total());
    }

    public function test_paginate_search_filters_by_name(): void
    {
        DripCampaign::factory()->create(['name' => 'Welcome Drip']);
        DripCampaign::factory()->create(['name' => 'Farewell Drip']);
        DripCampaign::factory()->create(['name' => 'Onboarding']);

        $result = $this->service->paginate(search: 'Drip');

        $this->assertSame(2, $result->total());
    }

    public function test_paginate_sorts_by_name_ascending(): void
    {
        DripCampaign::factory()->create(['name' => 'Zebra']);
        DripCampaign::factory()->create(['name' => 'Alpha']);
        DripCampaign::factory()->create(['name' => 'Middle']);

        $result = $this->service->paginate(sort: 'name', direction: 'asc');

        $this->assertSame('Alpha', $result->first()->name);
        $this->assertSame('Zebra', $result->last()->name);
    }

    public function test_paginate_sorts_by_created_at_descending(): void
    {
        $old = DripCampaign::factory()->create(['name' => 'Old']);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $new = DripCampaign::factory()->create(['name' => 'New']);

        $result = $this->service->paginate(sort: 'created_at', direction: 'desc');

        $this->assertSame('New', $result->first()->name);
    }

    public function test_paginate_ignores_invalid_sort_column(): void
    {
        DripCampaign::factory()->create(['name' => 'Alpha']);
        DripCampaign::factory()->create(['name' => 'Beta']);

        // 'invalid_column' should fallback to 'created_at'
        $result = $this->service->paginate(sort: 'invalid_column');

        $this->assertSame(2, $result->total());
    }

    public function test_paginate_status_filter(): void
    {
        DripCampaign::factory()->active()->count(2)->create();
        DripCampaign::factory()->inactive()->create();
        DripCampaign::factory()->create(); // draft

        $result = $this->service->paginate(status: ChatbotFlowStatus::Active->value);

        $this->assertSame(2, $result->total());
    }

    public function test_paginate_eager_loads_audience(): void
    {
        $audience = MailList::factory()->create(['name' => 'VIP List']);
        DripCampaign::factory()->create(['audience_id' => $audience->id]);

        $result = $this->service->paginate();
        $campaign = $result->first();

        $this->assertTrue($campaign->relationLoaded('audience'));
        $this->assertSame('VIP List', $campaign->audience->name);
    }

    public function test_paginate_includes_counts(): void
    {
        $campaign = DripCampaign::factory()->withStats(4)->create();

        $result = $this->service->paginate();
        $first = $result->first();

        $this->assertSame(4, $first->stats_count);
    }

    public function test_find_by_id_returns_drip_campaign(): void
    {
        $campaign = DripCampaign::factory()->create();

        $found = $this->service->findById($campaign->uuid);

        $this->assertNotNull($found);
        $this->assertSame($campaign->id, $found->id);
    }

    public function test_find_by_id_returns_null_for_chatbot_type(): void
    {
        $chatbot = ChatbotFlow::factory()->create();

        $found = $this->service->findById($chatbot->uuid);

        $this->assertNull($found);
    }

    public function test_paginate_respects_per_page(): void
    {
        DripCampaign::factory()->count(25)->create();

        $result = $this->service->paginate(perPage: 5);

        $this->assertSame(5, $result->perPage());
        $this->assertSame(25, $result->total());
        $this->assertCount(5, $result->items());
    }
}
