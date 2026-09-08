<?php

namespace Tests\Feature\Chatbot;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotInteractiveFlowTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        Queue::fake();

        $this->mock(InboxOutboundService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->andReturn(new Message([
                'id' => 999,
                'body' => 'mocked',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Text,
            ]));
            $mock->shouldReceive('sendMedia')->andReturn(new Message([
                'id' => 999,
                'body' => 'mocked',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Image,
            ]));
            $mock->shouldReceive('sendTypingIndicator')->andReturn(true);
        });

        $this->mock(WalletService::class, function ($mock): void {
            $mock->shouldReceive('balance')->andReturn(1000.0);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_quick_reply_matches_output_handle_by_text(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'menu',
                            'text' => 'Loading menu...',
                        ],
                    ],
                    [
                        'id' => 'interactive_1',
                        'type' => 'interactiveMessage',
                        'data' => [
                            'interactiveType' => 'button',
                            'bodyText' => 'Choose an option:',
                            'buttons' => [
                                ['id' => 'sales', 'title' => 'Sales'],
                                ['id' => 'support', 'title' => 'Support'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'sales_node',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Sales info'],
                    ],
                    [
                        'id' => 'support_node',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Support info'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'interactive_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'interactive_1', 'target' => 'sales_node', 'sourceHandle' => 'sales'],
                    ['source' => 'interactive_1', 'target' => 'support_node', 'sourceHandle' => 'support'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        // Trigger via welcomeMessage keyword → flows into interactive node (WaitForResponse)
        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'menu',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame('interactive_1', $state->current_node_id);
        $this->assertNotEmpty($state->variables['_interactive_options']);

        // Reply "sales" → should match the "sales" handle
        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'sales',
            'direction' => MessageDirection::Inbound,
        ]));

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
    }

    public function test_delay_node_dispatches_job_and_halts_execution(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'reminder',
                            'text' => 'Hold on...',
                        ],
                    ],
                    [
                        'id' => 'delay_1',
                        'type' => 'delay',
                        'data' => ['delaySeconds' => 30],
                    ],
                    [
                        'id' => 'followup',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Here is your info.'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'delay_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'delay_1', 'target' => 'followup', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'reminder',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame('fired', $result->value);

        // Delay halts execution. State stays Active (delayed job will resume it).
        // current_node_id points to the follow-up node for when the job runs.
        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Active, $state->status);
        $this->assertSame('followup', $state->current_node_id);

        // A delayed job should have been dispatched
        Queue::assertPushed(\App\Domains\Chatbot\Jobs\ProcessDelayedNodeJob::class);
    }

    public function test_typing_indicator_halts_with_delay(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hi',
                            'text' => 'Hello!',
                        ],
                    ],
                    [
                        'id' => 'typing_1',
                        'type' => 'typingIndicator',
                        'data' => ['duration' => 2],
                    ],
                    [
                        'id' => 'followup',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'How can we help?'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'typing_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'typing_1', 'target' => 'followup', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hi',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame('fired', $result->value);

        // Typing indicator dispatches a delayed job and halts execution (like delay node)
        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Active, $state->status);
        $this->assertSame('followup', $state->current_node_id);

        Queue::assertPushed(\App\Domains\Chatbot\Jobs\ProcessDelayedNodeJob::class);
    }

    public function test_multiple_flows_only_first_match_triggers(): void
    {
        ChatbotFlow::factory()->active()->create([
            'name' => 'First Bot',
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hello',
                            'text' => 'First bot says hello',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        ChatbotFlow::factory()->active()->create([
            'name' => 'Second Bot',
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_2',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hello',
                            'text' => 'Second bot says hello',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
        ]));

        // Only one flow state should be created (first match wins)
        $this->assertSame(1, ChatbotFlowState::query()->count());
    }
}
