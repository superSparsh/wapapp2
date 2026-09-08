<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateStatus;
use App\Models\Template;
use App\Models\Variable;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private string $serviceToken = 'default-template-service-secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['service-auth.token' => $this->serviceToken]);
    }

    public function test_tenant_data_is_strictly_isolated(): void
    {
        // Tenant A creates a template and a variable
        app(TenantContext::class)->setContext('tenant_alpha', 1, 1);
        $tplA = Template::query()->create([
            'tenant_id' => 'tenant_alpha',
            'name' => 'Alpha Template',
            'status' => TemplateStatus::Approved,
            'code' => 'alpha_tpl',
        ]);
        $varA = Variable::query()->create([
            'tenant_id' => 'tenant_alpha',
            'name' => 'alpha_var',
            'data_type' => 'string',
        ]);

        // Tenant B creates a template and a variable
        app(TenantContext::class)->setContext('tenant_beta', 2, 2);
        $tplB = Template::query()->create([
            'tenant_id' => 'tenant_beta',
            'name' => 'Beta Template',
            'status' => TemplateStatus::Approved,
            'code' => 'beta_tpl',
        ]);
        $varB = Variable::query()->create([
            'tenant_id' => 'tenant_beta',
            'name' => 'beta_var',
            'data_type' => 'string',
        ]);

        // Request as Tenant A
        $responseA = $this->getJson('/api/v1/templates', [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant_alpha',
            'Accept' => 'application/json',
        ]);

        $responseA->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Alpha Template');

        $varsA = $this->getJson('/api/v1/variables', [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant_alpha',
            'Accept' => 'application/json',
        ]);

        $varsA->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'alpha_var');

        // Request as Tenant B
        $responseB = $this->getJson('/api/v1/templates', [
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => 'tenant_beta',
            'Accept' => 'application/json',
        ]);

        $responseB->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Beta Template');
    }
}
