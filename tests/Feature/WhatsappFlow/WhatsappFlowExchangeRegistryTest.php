<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsappFlow;

use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowExchangeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappFlowExchangeRegistryTest extends TestCase
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

    public function test_publish_registers_central_exchange_token(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('whatsapp_flow_exchange_registry')) {
            $this->markTestSkipped('Central exchange registry table is not migrated in this environment.');
        }

        $flow = WhatsappFlow::factory()->withFlowJson()->create([
            'draft_synced_at' => now(),
        ]);

        $this->actingAsTenantUser()
            ->post(route('whatsapp-flows.publish', $flow))
            ->assertRedirect();

        $flow->refresh();

        $this->assertDatabaseHas('whatsapp_flow_exchange_registry', [
            'exchange_token' => $flow->exchange_token,
            'tenant_id' => $this->testTenant->id,
            'flow_id' => $flow->id,
        ]);
    }

    public function test_data_exchange_resolves_via_central_registry(): void
    {
        if (! \Illuminate\Support\Facades\Schema::connection(config('tenancy.database.central_connection', config('database.default')))->hasTable('whatsapp_flow_exchange_registry')) {
            $this->markTestSkipped('Central exchange registry table is not migrated in this environment.');
        }

        $flow = WhatsappFlow::factory()->active()->withFlowJson(2, 2)->create();

        WhatsappFlowExchangeRegistry::query()->create([
            'exchange_token' => $flow->exchange_token,
            'tenant_id' => $this->testTenant->id,
            'flow_id' => $flow->id,
        ]);

        $this->postJson(route('flow.exchange', $flow->exchange_token), [
            'phone_number' => '+911234567890',
            'data' => ['full_name' => 'Registry Test'],
        ])->assertOk();

        tenancy()->initialize($this->testTenant);

        $this->assertDatabaseHas('whatsapp_flow_submissions', [
            'whatsapp_flow_id' => $flow->id,
            'contact_phone' => '+911234567890',
        ]);
    }
}
