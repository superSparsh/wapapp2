<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_templates_index_uses_local_monolith(): void
    {
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
}
