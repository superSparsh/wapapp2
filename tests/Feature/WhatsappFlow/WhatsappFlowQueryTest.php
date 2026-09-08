<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsappFlow;

use App\Enums\WhatsappFlowStatus;
use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappFlowQueryTest extends TestCase
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

    public function test_pagination_works(): void
    {
        WhatsappFlow::factory()->count(15)->create();

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.index'))
            ->assertOk();

        // Default per_page is 10, so page 2 should also work
        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.index', ['page' => 2]))
            ->assertOk();
    }

    public function test_search_filters_results(): void
    {
        WhatsappFlow::factory()->create(['name' => 'Customer Survey']);
        WhatsappFlow::factory()->create(['name' => 'Lead Capture']);

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.index', ['search' => 'Survey']))
            ->assertOk()
            ->assertSee('Customer Survey')
            ->assertDontSee('Lead Capture');
    }

    public function test_status_filter_works(): void
    {
        WhatsappFlow::factory()->create(['name' => 'Draft Flow']);
        WhatsappFlow::factory()->active()->create(['name' => 'Active Flow']);

        $this->actingAsTenantUser()
            ->get(route('whatsapp-flows.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Active Flow')
            ->assertDontSee('Draft Flow');
    }

    public function test_active_flows_scope_returns_only_active(): void
    {
        WhatsappFlow::factory()->count(2)->create();
        WhatsappFlow::factory()->active()->count(3)->create();

        $active = WhatsappFlow::query()->active()->get();
        $this->assertCount(3, $active);
    }
}
