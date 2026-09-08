<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TemplateStatus;
use App\Enums\VariableDataType;
use App\Enums\VariableType;
use App\Models\Template;
use App\Models\Variable;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateApiTest extends TestCase
{
    use RefreshDatabase;

    private string $serviceToken = 'default-template-service-secret-token';
    private string $tenantId = 'tenant_tpl_test_123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['service-auth.token' => $this->serviceToken]);
        app(TenantContext::class)->setContext($this->tenantId, 1, 1, 'user-uuid', 'tm-uuid', 'Admin', 'Admin', 10, false);
    }

    private function authHeaders(array $extra = []): array
    {
        return array_merge([
            'X-Service-Token' => $this->serviceToken,
            'X-Tenant-Id' => $this->tenantId,
            'X-Actor-User-Id' => '1',
            'X-Actor-Team-Member-Id' => '1',
            'X-Whatsapp-Line-Id' => '10',
            'Accept' => 'application/json',
        ], $extra);
    }

    public function test_health_check_returns_healthy(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('service', 'template-service');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/templates');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'SERVICE_UNAUTHORIZED');
    }

    public function test_missing_tenant_header_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/templates', [
            'X-Service-Token' => $this->serviceToken,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('code', 'TENANT_HEADER_REQUIRED');
    }

    public function test_create_draft_and_list_templates(): void
    {
        $draft = $this->postJson('/api/v1/templates/draft', [], $this->authHeaders());
        $draft->assertStatus(201)
            ->assertJsonPath('template.status', 'draft')
            ->assertJsonStructure(['template' => ['uuid', 'name', 'payload']]);

        $list = $this->getJson('/api/v1/templates', $this->authHeaders());
        $list->assertOk()
            ->assertJsonCount(1, 'items');
    }

    public function test_create_from_setup_and_save_steps(): void
    {
        $create = $this->postJson('/api/v1/templates/from-setup', [
            'name' => 'Festival Discount Offer',
            'category' => 'MARKETING',
            'language' => 'en_GB',
            'template_type' => 'regular',
        ], $this->authHeaders());

        $create->assertStatus(201)
            ->assertJsonPath('template.name', 'Festival Discount Offer')
            ->assertJsonPath('template.code', 'festival_discount_offer');

        $uuid = $create->json('template.uuid');

        // Save body step
        $saveBody = $this->putJson("/api/v1/templates/{$uuid}/step/body", [
            'step_data' => [
                'text' => 'Hello $(first_name), check out our sale: $(discount_code)!',
                'samples' => ['Alice', 'SAVE20'],
            ],
        ], $this->authHeaders());

        $saveBody->assertOk()
            ->assertJsonPath('template.body_preview', 'Hello $(first_name), check out our sale: $(discount_code)!');

        // Submit template
        $submit = $this->postJson("/api/v1/templates/{$uuid}/submit", [], $this->authHeaders());
        $submit->assertOk()
            ->assertJsonPath('template.status', 'pending_review');

        // Preview
        $preview = $this->getJson('/api/v1/templates/preview/festival_discount_offer', $this->authHeaders());
        $preview->assertOk()
            ->assertJsonPath('title', 'Festival Discount Offer');
    }

    public function test_variable_crud(): void
    {
        $create = $this->postJson('/api/v1/variables', [
            'name' => 'customer_loyalty_points',
            'data_type' => 'number',
            'value' => '100',
        ], $this->authHeaders());

        $create->assertStatus(201)
            ->assertJsonPath('variable.name', 'customer_loyalty_points')
            ->assertJsonPath('variable.data_type', 'number');

        $uuid = $create->json('variable.uuid');

        // List variables
        $list = $this->getJson('/api/v1/variables', $this->authHeaders());
        $list->assertOk()
            ->assertJsonCount(1, 'items');

        // All variables (custom + builtin)
        $all = $this->getJson('/api/v1/variables/all', $this->authHeaders());
        $all->assertOk()
            ->assertJsonCount(1, 'custom')
            ->assertJsonStructure(['custom', 'builtin']);

        // Update variable
        $update = $this->putJson("/api/v1/variables/{$uuid}", [
            'name' => 'customer_points',
            'data_type' => 'number',
            'value' => '250',
        ], $this->authHeaders());

        $update->assertOk()
            ->assertJsonPath('variable.name', 'customer_points');

        // Delete variable
        $delete = $this->deleteJson("/api/v1/variables/{$uuid}", [], $this->authHeaders());
        $delete->assertOk()
            ->assertJsonPath('success', true);
    }
}
