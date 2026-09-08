<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class TemplateServiceAdapterTest extends TestCase
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
            'template-service.enabled' => true,
            'template-service.base_url' => 'http://127.0.0.1:8003/api/v1',
        ]);

        Http::fake([
            '*templates*' => Http::response([
                'items' => [
                    [
                        'id' => 999,
                        'uuid' => 'ms-tpl-1',
                        'name' => 'From Microservice Template',
                        'code' => 'from_microservice_template',
                        'status' => 'Approved',
                        'status_value' => 'approved',
                        'status_variant' => 'fd-approved',
                        'category' => 'Marketing',
                        'type' => 'Regular',
                        'created_at' => now()->format('Y-m-d h:i A'),
                        'error' => false,
                        'rejection_reason' => null,
                    ],
                ],
                'meta' => ['total' => 1, 'current_page' => 1, 'per_page' => 10],
                'categories' => ['MARKETING', 'UTILITY'],
                'types' => ['Regular', 'Draft'],
                'languages' => ['en_GB' => 'English (UK)'],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('From Microservice Template');
    }

    public function test_when_microservice_fails_gracefully_falls_back_to_local(): void
    {
        config([
            'template-service.enabled' => true,
            'template-service.fallback_to_local' => true,
            'template-service.base_url' => 'http://127.0.0.1:8003/api/v1',
        ]);

        Template::query()->create([
            'name' => 'Local Fallback Template',
            'code' => 'local_fallback_template',
            'status' => TemplateStatus::Approved,
            'whatsapp_line_id' => $this->testLine->id,
            'category' => 'MARKETING',
        ]);

        // Simulate microservice failure
        Http::fake([
            '*templates*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Local Fallback Template');
    }

    public function test_when_microservice_disabled_uses_local_directly(): void
    {
        config([
            'template-service.enabled' => false,
        ]);

        Template::query()->create([
            'name' => 'Direct Monolith Template',
            'code' => 'direct_monolith_template',
            'status' => TemplateStatus::Approved,
            'whatsapp_line_id' => $this->testLine->id,
            'category' => 'MARKETING',
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Direct Monolith Template');
    }

    public function test_options_uses_microservice_when_enabled(): void
    {
        config([
            'template-service.enabled' => true,
            'template-service.base_url' => 'http://127.0.0.1:8003/api/v1',
        ]);

        Http::fake([
            '*templates/options*' => Http::response([
                'items' => [
                    ['code' => 'promo_code', 'name' => 'Promo Code', 'language' => 'en_GB', 'category' => 'MARKETING'],
                ],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.api.list'))
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Promo Code');
    }

    public function test_variables_uses_microservice_when_enabled(): void
    {
        config([
            'template-service.enabled' => true,
            'template-service.base_url' => 'http://127.0.0.1:8003/api/v1',
        ]);

        Http::fake([
            '*variables/all*' => Http::response([
                'custom' => [
                    ['name' => 'order_total', 'type' => 'custom'],
                ],
                'builtin' => [
                    ['name' => 'first_name', 'type' => 'built-in'],
                ],
            ], 200),
        ]);

        $this->actingAsTenantUser()
            ->get(route('templates.api.variables'))
            ->assertOk()
            ->assertJsonPath('custom.0.name', 'order_total');
    }
}
