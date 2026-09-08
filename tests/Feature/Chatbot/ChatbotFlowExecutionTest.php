<?php

namespace Tests\Feature\Chatbot;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotFlowExecutionTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        // Mock outbound service to prevent actual message sending
        $this->mock(InboxOutboundService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->andReturn(new Message([
                'id' => 999,
                'body' => 'mocked',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Text,
            ]));
            $mock->shouldReceive('sendTemplate')->andReturn(new Message([
                'id' => 999,
                'body' => 'mocked',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Text,
            ]));
        });

        // Ensure wallet balance is sufficient
        $this->mock(WalletService::class, function ($mock): void {
            $mock->shouldReceive('balance')->andReturn(1000.0);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_no_match_when_no_flows_exist(): void
    {
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, $message);

        $this->assertSame('no_match', $result->value);
        $this->assertSame(0, ChatbotFlowState::query()->count());
    }

    public function test_keyword_trigger_creates_state_and_processes_flow(): void
    {
        $flow = ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hello',
                            'text' => 'Welcome! How can we help?',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, $message);

        $this->assertSame('fired', $result->value);

        // State should be completed (no wait nodes)
        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame($flow->id, $state->chatbot_flow_id);
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
    }

    public function test_keyword_does_not_match_inactive_flow(): void
    {
        ChatbotFlow::factory()->inactive()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hello',
                            'text' => 'Welcome!',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, $message);

        $this->assertSame('no_match', $result->value);
    }

    public function test_wait_for_response_node_pauses_execution(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'survey',
                            'text' => 'Rate us 1-5:',
                        ],
                    ],
                    [
                        'id' => 'wait_1',
                        'type' => 'waitForResponse',
                        'data' => ['variableName' => 'rating'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'wait_1', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'survey',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, $message);

        $this->assertSame('fired', $result->value);

        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('wait_1', $state->current_node_id);
        $this->assertSame('rating', $state->variables['_wait_variable_name']);
    }

    public function test_reply_to_waiting_state_resumes_flow(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'survey',
                            'text' => 'Rate us:',
                        ],
                    ],
                    [
                        'id' => 'wait_1',
                        'type' => 'waitForResponse',
                        'data' => ['variableName' => 'rating'],
                    ],
                    [
                        'id' => 'thanks_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'text' => 'Thank you for your feedback!',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'wait_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'wait_1', 'target' => 'thanks_1', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        // 1. Trigger the flow (pauses at wait_1)
        $triggerMsg = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'survey',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $engine->processInbound($conversation, $triggerMsg);

        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);

        // 2. Reply to the waiting state
        $replyMsg = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => '5',
            'direction' => MessageDirection::Inbound,
        ]);

        $result = $engine->processInbound($conversation, $replyMsg);

        $this->assertSame('fired', $result->value);

        // State should now be completed
        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
        $this->assertSame('5', $state->variables['rating']);
    }

    public function test_start_command_resets_existing_states(): void
    {
        $flow = ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'start',
                            'text' => 'Started fresh!',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        // Create an existing waiting state
        ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'wait_1',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addMinutes(5),
        ]);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'start',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, $message);

        $this->assertSame('fired', $result->value);

        // Previous state should be expired
        $states = ChatbotFlowState::query()->orderBy('id')->get();
        $this->assertSame(ChatbotFlowStateStatus::Expired, $states->first()->status);
        $this->assertSame(ChatbotFlowStateStatus::Completed, $states->last()->status);
    }

    public function test_flow_without_nodes_returns_no_match(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => ['nodes' => [], 'edges' => []],
        ]);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, $message);

        $this->assertSame('no_match', $result->value);
    }

    public function test_only_exact_keyword_matches_trigger_flow(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hello',
                            'text' => 'Welcome!',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'say hello world',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, $message);

        // Partial match should NOT trigger
        $this->assertSame('no_match', $result->value);
    }

    /**
     * Helper to create a mock Message if the factory can't be used with the tenant setup.
     */
    private function createInboundMessage(Conversation $conversation, string $body): Message
    {
        return Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => $body,
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }
}
