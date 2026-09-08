<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsappFlow;

use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsappFlowBuilderTest extends TestCase
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

    public function test_get_data_returns_flow_json(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson(2, 3)->create();

        $response = $this->actingAsTenantUser()
            ->getJson(route('whatsapp-flows.data', $flow))
            ->assertOk()
            ->assertJsonStructure(['success', 'flow' => ['id', 'uuid', 'name', 'flow_json']]);

        $this->assertTrue($response->json('success'));
    }

    public function test_save_data_stores_flow_json(): void
    {
        $flow = WhatsappFlow::factory()->create();
        $json = [
            'screens' => [
                ['id' => 's1', 'title' => 'First', 'fields' => [['name' => 'f1', 'type' => 'text', 'label' => 'Name']], 'next_screen' => null, 'conditions' => []],
            ],
            'first_screen' => 's1',
        ];

        $this->actingAsTenantUser()
            ->putJson(route('whatsapp-flows.data.save', $flow), ['flow_json' => $json])
            ->assertOk()
            ->assertJson(['success' => true, 'screen_count' => 1, 'field_count' => 1, 'draft_synced' => true]);

        $flow->refresh();
        $this->assertSame('First', $flow->flow_json['screens'][0]['title']);
        $this->assertNotNull($flow->draft_synced_at);
        $this->assertNotNull($flow->meta_json);
        $this->assertNotNull($flow->json_asset_path);
    }

    public function test_save_data_with_multiple_screens(): void
    {
        $flow = WhatsappFlow::factory()->create();
        $json = [
            'screens' => [
                ['id' => 's1', 'title' => 'First', 'fields' => [], 'next_screen' => 's2', 'conditions' => []],
                ['id' => 's2', 'title' => 'Second', 'fields' => [], 'next_screen' => null, 'conditions' => []],
            ],
            'first_screen' => 's1',
        ];

        $this->actingAsTenantUser()
            ->putJson(route('whatsapp-flows.data.save', $flow), ['flow_json' => $json])
            ->assertOk()
            ->assertJson(['success' => true, 'screen_count' => 2]);
    }

    public function test_export_returns_json_download(): void
    {
        $flow = WhatsappFlow::factory()->withFlowJson()->create();

        $this->actingAsTenantUser()
            ->getJson(route('whatsapp-flows.export', $flow))
            ->assertOk()
            ->assertJsonStructure(['flow' => ['name', 'status', 'flow_json', 'exported_at']]);
    }

    public function test_import_creates_new_flow(): void
    {
        $json = [
            'screens' => [
                ['id' => 's1', 'title' => 'Imported', 'fields' => [], 'next_screen' => null, 'conditions' => []],
            ],
            'first_screen' => 's1',
        ];

        $this->actingAsTenantUser()
            ->postJson(route('whatsapp-flows.import'), ['flow_json' => $json])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('whatsapp_flows', 1);
    }

    public function test_get_data_returns_empty_structure_for_new_flow(): void
    {
        $flow = WhatsappFlow::factory()->create();

        $response = $this->actingAsTenantUser()
            ->getJson(route('whatsapp-flows.data', $flow))
            ->assertOk();

        $this->assertEmpty($response->json('flow.flow_json.screens'));
    }
}
