<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Models\Campaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_when_microservice_enabled_index_uses_microservice(): void
    {
        config([
            'campaign-service.enabled' => true,
            'campaign-service.base_url' => 'http://127.0.0.1:8002/api/v1',
        ]);

        Http::fake([
            '*campaigns*' => Http::response([
                'items' => [
                    [
                        'id' => 999,
                        'uuid' => 'ms-camp-1',
                        'name' => 'From Microservice Campaign',
                        'status' => 'draft',
                        'total_recipients' => 10,
                        'total_delivered' => 0,
                        'created_at' => now()->toISOString(),
                    ],
                ],
                'meta' => ['total' => 1, 'current_page' => 1, 'per_page' => 10],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertSee('From Microservice Campaign');
    }

    public function test_when_microservice_fails_gracefully_falls_back_to_local(): void
    {
        config([
            'campaign-service.enabled' => true,
            'campaign-service.fallback_to_local' => true,
            'campaign-service.base_url' => 'http://127.0.0.1:8002/api/v1',
        ]);

        Campaign::factory()->create([
            'name' => 'Local Fallback Campaign',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        // Simulate microservice failure
        Http::fake([
            '*campaigns*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $this->actingAsTenantUser()
            ->get(route('campaigns.index'))
            ->assertOk()
            ->assertSee('Local Fallback Campaign');
    }

    public function test_when_microservice_disabled_uses_local_directly(): void
    {
        config([
            'campaign-service.enabled' => false,
        ]);

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
