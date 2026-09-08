<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Campaigns\Contracts\CampaignServiceClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignServiceClientTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private CampaignServiceClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->client = app(CampaignServiceClientInterface::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_health_check_returns_true_when_healthy(): void
    {
        Http::fake([
            '*health*' => Http::response(['status' => 'healthy', 'service' => 'campaign-service'], 200),
        ]);

        $this->assertTrue($this->client->isHealthy());
    }

    public function test_list_campaigns_sends_correct_headers(): void
    {
        Http::fake([
            '*campaigns*' => Http::response([
                'items' => [
                    ['uuid' => 'camp-123', 'name' => 'Spring Festival Sale', 'total_recipients' => 50],
                ],
                'meta' => ['total' => 1, 'current_page' => 1, 'per_page' => 10],
            ], 200),
        ]);

        $result = $this->client->listCampaigns(['search' => 'Spring']);

        $this->assertCount(1, $result['items']);
        $this->assertSame('Spring Festival Sale', $result['items'][0]['name']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/campaigns')
                && $request->hasHeader('X-Service-Token')
                && $request->hasHeader('X-Tenant-Id');
        });
    }

    public function test_create_campaign_calls_microservice(): void
    {
        Http::fake([
            '*campaigns*' => Http::response([
                'campaign' => [
                    'uuid' => 'camp-new-uuid',
                    'name' => 'New Year Offer',
                    'status' => 'draft',
                ],
                'message' => 'Campaign created successfully.',
            ], 201),
        ]);

        $result = $this->client->createCampaign([
            'name' => 'New Year Offer',
            'whatsapp_line_id' => 1,
            'template_id' => 2,
        ]);

        $this->assertSame('camp-new-uuid', $result['campaign']['uuid']);
        $this->assertSame('New Year Offer', $result['campaign']['name']);
    }
}
