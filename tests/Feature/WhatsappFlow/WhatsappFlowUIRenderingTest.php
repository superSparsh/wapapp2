<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsappFlow;

use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappFlowUIRenderingTest extends TestCase
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

    public function test_index_page_renders(): void
    {
        WhatsappFlow::factory()->count(3)->create();

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.index'))
            ->assertOk()
            ->assertSee('WhatsApp Flows');
    }

    public function test_create_form_renders(): void
    {
        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.create'))
            ->assertOk()
            ->assertSee('Create WhatsApp Flow');
    }

    public function test_show_page_renders_with_flow_details(): void
    {
        $flow = WhatsappFlow::factory()->active()->withFlowJson()->withSubmissions(2)->create();

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.show', $flow))
            ->assertOk()
            ->assertSee($flow->name)
            ->assertSee('Active');
    }

    public function test_builder_page_renders(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson(2, 3)->create();

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.edit', $flow))
            ->assertOk()
            ->assertSee($flow->name)
            ->assertSee('Save Flow');
    }

    public function test_stats_page_renders_with_data(): void
    {
        $flow = WhatsappFlow::factory()->withSubmissions(5)->create();

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.stats', $flow))
            ->assertOk()
            ->assertSee($flow->name)
            ->assertSee('Stats');
    }
}
