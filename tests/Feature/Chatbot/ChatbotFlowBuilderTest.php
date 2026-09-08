<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotFlowBuilderTest extends TestCase
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

    /**
     * @return array{nodes: array<int, array<string, mixed>>, edges: array<int, array<string, mixed>>, viewport?: array<string, mixed>}
     */
    private function sampleFlowPayload(): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'welcome_1',
                    'type' => 'welcomeMessage',
                    'position' => ['x' => 100, 'y' => 100],
                    'data' => [
                        'label' => 'Welcome',
                        'triggerKeyword' => 'hello',
                        'welcomeMessage' => 'Hello!',
                        'text' => 'Hello!',
                    ],
                ],
            ],
            'edges' => [],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    public function test_store_json_returns_edit_url(): void
    {
        $response = $this->actingAsTenantUser()
            ->postJson(route('chatbot.store'), ['name' => 'JSON Bot']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('edit_url', fn (string $url) => str_contains($url, '/automation/chatbot/'));
    }

    public function test_builder_data_returns_legacy_shape(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'exported_data' => $this->sampleFlowPayload(),
        ]);

        $this->actingAsTenantUser()
            ->getJson(route('chatbot.builder-data', $flow))
            ->assertOk()
            ->assertJsonPath('message', 'success')
            ->assertJsonPath('automationBot.uuid', $flow->uuid)
            ->assertJsonStructure([
                'templates',
                'interactiveMessages',
                'automationBot' => ['id', 'uuid', 'name', 'exported_data', 'guided_tour'],
            ]);
    }

    public function test_legacy_custom_data_save_round_trip(): void
    {
        $flow = ChatbotFlow::factory()->create();
        $payload = $this->sampleFlowPayload();

        $this->actingAsTenantUser()
            ->postJson(route('chatbot.data.save', $flow), [
                'customData' => json_encode($payload),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $flow->refresh();
        $this->assertSame('hello', $flow->exported_data['nodes'][0]['data']['triggerKeyword']);
        $this->assertSame(1, $flow->exported_data['viewport']['zoom']);
    }

    public function test_save_and_load_flow_data_round_trip(): void
    {
        $flow = ChatbotFlow::factory()->create();
        $payload = $this->sampleFlowPayload();

        $this->actingAsTenantUser()
            ->postJson(route('chatbot.data.save', $flow), $payload)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAsTenantUser()
            ->getJson(route('chatbot.data', $flow))
            ->assertOk()
            ->assertJsonPath('data.nodes.0.data.triggerKeyword', 'hello');
    }

    public function test_save_validation_errors_return_422_for_missing_nodes(): void
    {
        $flow = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->postJson(route('chatbot.data.save', $flow), [
                'edges' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_edit_page_renders_react_builder_mount(): void
    {
        $flow = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('chatbot.edit', $flow))
            ->assertOk()
            ->assertSee('id="chatbot-react-root"', false)
            ->assertSee(route('chatbot.builder-data', $flow), false);
    }

    public function test_publish_is_blocked_without_trigger_keyword(): void
    {
        $flow = ChatbotFlow::factory()->withNodes(1)->create();

        $this->actingAsTenantUser()
            ->post(route('chatbot.publish', $flow))
            ->assertRedirect()
            ->assertSessionHasErrors('flow');

        $flow->refresh();
        $this->assertNotSame('active', $flow->status->value);
    }

    public function test_publish_succeeds_with_valid_welcome_flow(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'exported_data' => $this->sampleFlowPayload(),
        ]);

        $this->actingAsTenantUser()
            ->post(route('chatbot.publish', $flow))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $flow->refresh();
        $this->assertSame('active', $flow->status->value);
        $this->assertNotNull($flow->published_at);
    }

    public function test_import_into_existing_flow_replaces_canvas(): void
    {
        $flow = ChatbotFlow::factory()->create();
        $payload = $this->sampleFlowPayload();

        $this->actingAsTenantUser()
            ->postJson(route('chatbot.import.flow', $flow), [
                'exported_data' => $payload,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nodes.0.data.triggerKeyword', 'hello');

        $flow->refresh();
        $this->assertSame(1, $flow->nodeCount());
    }

    public function test_clear_cache_endpoint_returns_success(): void
    {
        $flow = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->postJson(route('chatbot.clear-cache', $flow))
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_toggle_activation_requires_valid_flow(): void
    {
        $flow = ChatbotFlow::factory()->create(['status' => 'draft']);

        $this->actingAsTenantUser()
            ->patch(route('chatbot.toggle', $flow))
            ->assertRedirect()
            ->assertSessionHasErrors('flow');
    }
}
