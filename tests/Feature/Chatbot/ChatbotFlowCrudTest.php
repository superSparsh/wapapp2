<?php

namespace Tests\Feature\Chatbot;

use App\Models\ChatbotFlow;
use Database\Factories\ChatbotFlowFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotFlowCrudTest extends TestCase
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

    public function test_index_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('chatbot.index'))
            ->assertOk()
            ->assertSee('Chatbot');
    }

    public function test_create_page_loads(): void
    {
        $this->actingAsTenantUser()
            ->get(route('chatbot.create'))
            ->assertOk()
            ->assertSee('Create Chatbot Flow')
            ->assertSee('Test Line');
    }

    public function test_store_creates_flow(): void
    {
        $this->actingAsTenantUser()
            ->post(route('chatbot.store'), ['name' => 'Test Bot'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatbot_flows', ['name' => 'Test Bot']);
    }

    public function test_store_validates_name_required(): void
    {
        $this->actingAsTenantUser()
            ->post(route('chatbot.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_show_page_loads(): void
    {
        $flow = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('chatbot.show', $flow))
            ->assertOk()
            ->assertSee($flow->name);
    }

    public function test_edit_page_loads(): void
    {
        $flow = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->get(route('chatbot.edit', $flow))
            ->assertOk()
            ->assertSee($flow->name)
            ->assertSee('id="chatbot-react-root"', false);
    }

    public function test_update_modifies_flow(): void
    {
        $flow = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->put(route('chatbot.update', $flow), ['name' => 'Updated Bot Name'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatbot_flows', ['id' => $flow->id, 'name' => 'Updated Bot Name']);
    }

    public function test_destroy_deletes_flow(): void
    {
        $flow = ChatbotFlow::factory()->create();

        $this->actingAsTenantUser()
            ->delete(route('chatbot.destroy', $flow))
            ->assertRedirect();

        $this->assertSoftDeleted('chatbot_flows', ['id' => $flow->id]);
    }

    public function test_toggle_changes_status(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'status' => 'draft',
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'label' => 'Welcome',
                            'message' => 'Hello',
                            'keywords' => 'hello',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $this->actingAsTenantUser()
            ->patch(route('chatbot.toggle', $flow))
            ->assertRedirect();

        $flow->refresh();
        $this->assertSame('active', $flow->status->value);
    }

    public function test_toggle_returns_json_for_ajax_requests(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'status' => 'draft',
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'label' => 'Welcome',
                            'message' => 'Hello',
                            'keywords' => 'hello',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $this->actingAsTenantUser()
            ->patchJson(route('chatbot.toggle', $flow))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'active' => true,
                'status' => 'activated',
            ]);

        $flow->refresh();
        $this->assertSame('active', $flow->status->value);
    }

    public function test_toggle_deactivates_active_flow(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'status' => 'active',
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'label' => 'Welcome',
                            'message' => 'Hello',
                            'keywords' => 'hello',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $this->actingAsTenantUser()
            ->patchJson(route('chatbot.toggle', $flow))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'active' => false,
                'status' => 'deactivated',
            ]);

        $flow->refresh();
        $this->assertSame('inactive', $flow->status->value);
    }

    public function test_toggle_activation_requires_trigger_keywords(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'status' => 'draft',
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'label' => 'Welcome',
                            'message' => 'Hello',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $this->actingAsTenantUser()
            ->patchJson(route('chatbot.toggle', $flow))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['flow']);
    }

    public function test_publish_sets_active_and_timestamp(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'label' => 'Welcome',
                            'message' => 'Hello',
                            'keywords' => 'hello',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $this->actingAsTenantUser()
            ->post(route('chatbot.publish', $flow))
            ->assertRedirect();

        $flow->refresh();
        $this->assertSame('active', $flow->status->value);
        $this->assertNotNull($flow->published_at);
    }

    public function test_duplicate_creates_copy(): void
    {
        $flow = ChatbotFlow::factory()->withNodes(2)->create(['name' => 'Original Bot']);

        $this->actingAsTenantUser()
            ->post(route('chatbot.duplicate', $flow))
            ->assertRedirect();

        $this->assertDatabaseCount('chatbot_flows', 2);
    }

    public function test_unauthenticated_access_redirects(): void
    {
        $this->get(route('chatbot.index'))->assertRedirect(route('login'));
    }
}
