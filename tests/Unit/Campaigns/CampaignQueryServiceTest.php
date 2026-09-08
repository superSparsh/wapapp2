<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Campaigns\Services\CampaignQueryService;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\MailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignQueryServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CampaignQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new CampaignQueryService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_paginate_returns_campaigns(): void
    {
        Campaign::factory()->count(3)->create();

        $result = $this->service->paginate();

        $this->assertSame(3, $result->total());
    }

    public function test_paginate_search_filters_by_name(): void
    {
        Campaign::factory()->create(['name' => 'Welcome Campaign']);
        Campaign::factory()->create(['name' => 'Farewell Campaign']);
        Campaign::factory()->create(['name' => 'Onboarding']);

        $result = $this->service->paginate(search: 'Campaign');

        $this->assertSame(2, $result->total());
    }

    public function test_paginate_sorts_by_name_ascending(): void
    {
        Campaign::factory()->create(['name' => 'Zebra']);
        Campaign::factory()->create(['name' => 'Alpha']);
        Campaign::factory()->create(['name' => 'Middle']);

        $result = $this->service->paginate(sort: 'name', direction: 'asc');

        $this->assertSame('Alpha', $result->first()->name);
        $this->assertSame('Zebra', $result->last()->name);
    }

    public function test_paginate_sorts_by_created_at_descending(): void
    {
        $old = Campaign::factory()->create(['name' => 'Old']);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $new = Campaign::factory()->create(['name' => 'New']);

        $result = $this->service->paginate(sort: 'created_at', direction: 'desc');

        $this->assertSame('New', $result->first()->name);
    }

    public function test_paginate_ignores_invalid_sort_column(): void
    {
        Campaign::factory()->create(['name' => 'Alpha']);
        Campaign::factory()->create(['name' => 'Beta']);

        $result = $this->service->paginate(sort: 'invalid_column');

        $this->assertSame(2, $result->total());
    }

    public function test_paginate_status_filter(): void
    {
        Campaign::factory()->sending()->count(2)->create();
        Campaign::factory()->draft()->create();
        Campaign::factory()->completed()->create();

        $result = $this->service->paginate(status: CampaignStatus::Sending->value);

        $this->assertSame(2, $result->total());
    }

    public function test_paginate_eager_loads_audience(): void
    {
        $audience = MailList::factory()->create(['name' => 'VIP List']);
        Campaign::factory()->create(['audience_id' => $audience->id]);

        $result = $this->service->paginate();
        $campaign = $result->first();

        $this->assertTrue($campaign->relationLoaded('audience'));
        $this->assertSame('VIP List', $campaign->audience->name);
    }

    public function test_paginate_respects_per_page(): void
    {
        Campaign::factory()->count(25)->create();

        $result = $this->service->paginate(perPage: 5);

        $this->assertSame(5, $result->perPage());
        $this->assertSame(25, $result->total());
        $this->assertCount(5, $result->items());
    }

    public function test_find_by_uuid_returns_campaign(): void
    {
        $campaign = Campaign::factory()->create();

        $found = $this->service->findByUuid($campaign->uuid);

        $this->assertNotNull($found);
        $this->assertSame($campaign->id, $found->id);
    }

    public function test_find_by_uuid_returns_null_for_missing(): void
    {
        $found = $this->service->findByUuid('non-existent-uuid');

        $this->assertNull($found);
    }

    public function test_empty_results_return_empty_paginator(): void
    {
        $result = $this->service->paginate();

        $this->assertSame(0, $result->total());
        $this->assertEmpty($result->items());
    }
}
