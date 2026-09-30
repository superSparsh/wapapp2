<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignWorkerRefTest extends TestCase
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

    public function test_creating_campaign_assigns_global_worker_ref(): void
    {
        $campaign = Campaign::factory()->create(['name' => 'Ref One']);

        $this->assertNotNull($campaign->fresh()->worker_ref);
        $this->assertGreaterThan(0, (int) $campaign->worker_ref);

        $centralId = tenancy()->central(fn () => DB::table('campaign_worker_refs')
            ->where('tenant_id', $this->testTenant->id)
            ->where('tenant_campaign_id', $campaign->id)
            ->value('id'));

        $this->assertSame((int) $campaign->worker_ref, (int) $centralId);
    }

    public function test_worker_refs_are_unique_per_campaign(): void
    {
        $first = Campaign::factory()->create(['name' => 'A']);
        $second = Campaign::factory()->create(['name' => 'B']);

        $this->assertGreaterThan(0, (int) $first->worker_ref);
        $this->assertGreaterThan(0, (int) $second->worker_ref);
        $this->assertNotSame((int) $first->worker_ref, (int) $second->worker_ref);
    }

    public function test_duplicate_gets_new_worker_ref(): void
    {
        $original = Campaign::factory()->create(['name' => 'Original']);
        $copy = app(\App\Domains\Campaigns\Services\CampaignService::class)->duplicate($original);

        $this->assertNotNull($copy->worker_ref);
        $this->assertNotSame((int) $original->worker_ref, (int) $copy->worker_ref);
    }
}
