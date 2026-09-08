<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private string $serviceToken = 'default-campaign-service-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['service-auth.token' => $this->serviceToken]);
    }

    public function test_tenant_a_cannot_see_or_modify_tenant_b_campaigns(): void
    {
        // Tenant A creates campaign
        app(TenantContext::class)->setContext('tenant_alpha', 1, 1);
        $campaignAlpha = Campaign::query()->create([
            'tenant_id' => 'tenant_alpha',
            'name' => 'Alpha Special Offer',
            'status' => CampaignStatus::Draft,
        ]);

        // Tenant B creates campaign
        app(TenantContext::class)->setContext('tenant_beta', 2, 2);
        $campaignBeta = Campaign::query()->create([
            'tenant_id' => 'tenant_beta',
            'name' => 'Beta Product Update',
            'status' => CampaignStatus::Draft,
        ]);

        // Query as Tenant Alpha
        $responseAlpha = $this->getJson('/api/v1/campaigns', [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant_alpha',
            'Accept' => 'application/json',
        ]);

        $responseAlpha->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Alpha Special Offer');

        // Query as Tenant Beta
        $responseBeta = $this->getJson('/api/v1/campaigns', [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant_beta',
            'Accept' => 'application/json',
        ]);

        $responseBeta->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Beta Product Update');

        // Tenant Beta attempting to access Tenant Alpha's campaign directly returns 404
        $crossAccess = $this->getJson("/api/v1/campaigns/{$campaignAlpha->uuid}", [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant_beta',
            'Accept' => 'application/json',
        ]);

        $crossAccess->assertStatus(404);
    }
}
