<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Models\DripCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripFlowBuilderTest extends TestCase
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

    public function test_flow_data_returns_empty_nodes_by_default(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->getJson(route('automation.drip.flow.data', $campaign))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['nodes' => [], 'edges' => []],
            ]);
    }

    public function test_flow_save_persists_nodes(): void
    {
        $campaign = DripCampaign::factory()->create();
        $nodes = [
            [
                'id' => 'node_test_1',
                'type' => 'templateMessage',
                'data' => [
                    'label' => 'Send welcome template',
                    'template_name' => 'welcome_v1',
                    'variables' => ['{{name}}'],
                    'message' => '',
                    'keywords' => '',
                ],
            ],
            [
                'id' => 'node_test_2',
                'type' => 'delay',
                'data' => [
                    'label' => 'Wait 1 day',
                    'delay_seconds' => 86400,
                    'message' => '',
                ],
            ],
        ];

        $this->actingAsTenantUser()
            ->postJson(route('automation.drip.flow.save', $campaign), [
                'nodes' => $nodes,
                'edges' => [],
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'node_count' => 2,
            ]);

        $campaign->refresh();
        $this->assertCount(2, $campaign->exported_data['nodes']);
        $this->assertSame('welcome_v1', $campaign->exported_data['nodes'][0]['data']['template_name']);
        $this->assertSame(86400, $campaign->exported_data['nodes'][1]['data']['delay_seconds']);
    }

    public function test_design_page_includes_flow_editor_markup(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.design', $campaign))
            ->assertOk()
            ->assertSee('data-drip-flow-editor', false)
            ->assertSee('data-drip-node-picker', false)
            ->assertSee('data-drip-node-config-panel', false)
            ->assertSee('node-config.js', false)
            ->assertSee('Save Flow', false)
            ->assertSee('Add an Action', false)
            ->assertSee('Streamline your communication', false);
    }

    public function test_design_page_includes_categorized_node_picker(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('automation.drip.design', $campaign))
            ->assertOk()
            ->assertSee('Send a template', false)
            ->assertSee('Evaluate a condition', false)
            ->assertSee('Operation', false)
            ->assertSee('Wait', false);
    }

    public function test_flow_save_rejects_incomplete_nodes(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->actingAsTenantUser()
            ->postJson(route('automation.drip.flow.save', $campaign), [
                'nodes' => [
                    [
                        'id' => 'node_invalid',
                        'type' => 'templateMessage',
                        'data' => [
                            'label' => 'Missing template',
                            'template_name' => '',
                        ],
                    ],
                ],
                'edges' => [],
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_unauthenticated_cannot_save_flow(): void
    {
        $campaign = DripCampaign::factory()->create();

        $this->postJson(route('automation.drip.flow.save', $campaign), [
            'nodes' => [],
            'edges' => [],
        ])->assertRedirect(route('login'));
    }
}
