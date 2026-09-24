<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignServiceAdapterTest extends TestCase
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

    public function test_campaign_index_uses_local_monolith(): void
    {
        Campaign::factory()->create([
            'name' => 'Direct Monolith Campaign',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertSee('Direct Monolith Campaign');
    }
}
