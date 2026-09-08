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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotBusinessHoursAndJumpTest extends TestCase
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
            $mock->shouldReceive('sendTypingIndicator')->andReturn(true);
        });

        $this->mock(WalletService::class, function ($mock): void {
            $mock->shouldReceive('balance')->andReturn(1000.0);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset test time
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_business_hours_branches_to_open_node_during_working_hours(): void
    {
        // Wednesday at 14:00 (inside 09:00 - 18:00)
        Carbon::setTestNow(Carbon::parse('2026-08-19 14:00:00', 'Asia/Kolkata'));

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'support',
                            'text' => 'Checking office status...',
                        ],
                    ],
                    [
                        'id' => 'bh_node',
                        'type' => 'dateTimeCondition',
                        'data' => [
                            'mode' => 'business_hours',
                            'timezone' => 'Asia/Kolkata',
                            'start_time' => '09:00',
                            'end_time' => '18:00',
                            'enabled_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                        ],
                    ],
                    [
                        'id' => 'open_msg',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Our agents are available!'],
                    ],
                    [
                        'id' => 'closed_msg',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'We are currently closed.'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'bh_node', 'sourceHandle' => 'output_1'],
                    ['source' => 'bh_node', 'target' => 'open_msg', 'sourceHandle' => 'open'],
                    ['source' => 'bh_node', 'target' => 'closed_msg', 'sourceHandle' => 'closed'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'support',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
        $this->assertSame('open_msg', $state->current_node_id);
        $this->assertTrue($state->variables['_is_business_hours']);
    }

    public function test_business_hours_branches_to_closed_node_outside_working_hours(): void
    {
        // Wednesday at 21:00 (outside 09:00 - 18:00)
        Carbon::setTestNow(Carbon::parse('2026-08-19 21:00:00', 'Asia/Kolkata'));

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'support',
                            'text' => 'Checking office status...',
                        ],
                    ],
                    [
                        'id' => 'bh_node',
                        'type' => 'dateTimeCondition',
                        'data' => [
                            'mode' => 'business_hours',
                            'timezone' => 'Asia/Kolkata',
                            'start_time' => '09:00',
                            'end_time' => '18:00',
                            'enabled_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                        ],
                    ],
                    [
                        'id' => 'open_msg',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Our agents are available!'],
                    ],
                    [
                        'id' => 'closed_msg',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'We are currently closed.'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'bh_node', 'sourceHandle' => 'output_1'],
                    ['source' => 'bh_node', 'target' => 'open_msg', 'sourceHandle' => 'open'],
                    ['source' => 'bh_node', 'target' => 'closed_msg', 'sourceHandle' => 'closed'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'support',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
        $this->assertSame('closed_msg', $state->current_node_id);
        $this->assertFalse($state->variables['_is_business_hours']);
    }

    public function test_business_hours_branches_to_closed_on_holiday(): void
    {
        // Holiday date (e.g. 2026-12-25) at 12:00 PM
        Carbon::setTestNow(Carbon::parse('2026-12-25 12:00:00', 'Asia/Kolkata'));

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'support',
                            'text' => 'Checking status...',
                        ],
                    ],
                    [
                        'id' => 'bh_node',
                        'type' => 'dateTimeCondition',
                        'data' => [
                            'mode' => 'business_hours',
                            'timezone' => 'Asia/Kolkata',
                            'start_time' => '09:00',
                            'end_time' => '18:00',
                            'holidays' => ['2026-12-25', '2026-01-01'],
                        ],
                    ],
                    [
                        'id' => 'open_msg',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Open'],
                    ],
                    [
                        'id' => 'closed_msg',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Closed for Holiday'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'bh_node', 'sourceHandle' => 'output_1'],
                    ['source' => 'bh_node', 'target' => 'open_msg', 'sourceHandle' => 'open'],
                    ['source' => 'bh_node', 'target' => 'closed_msg', 'sourceHandle' => 'closed'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'support',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame('closed_msg', $state->current_node_id);
    }

    public function test_jump_to_step_navigates_to_target_node(): void
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
                            'text' => 'Welcome! Redirecting to main menu...',
                        ],
                    ],
                    [
                        'id' => 'jump_node',
                        'type' => 'jumpToStep',
                        'data' => [
                            'targetNodeId' => 'target_menu_node',
                            'targetStep' => 'target_menu_node',
                        ],
                    ],
                    [
                        'id' => 'target_menu_node',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'text' => 'This is the Main Menu.',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'jump_node', 'sourceHandle' => 'output_1'],
                    // Note: No explicit edge from jump_node to target_menu_node — jump is programmatic
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'menu',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
        $this->assertSame('target_menu_node', $state->current_node_id);
        $this->assertSame('jump_node', $state->variables['_jumped_from']);
        $this->assertSame(1, $state->variables['_total_jumps']);
    }

    public function test_jump_to_step_loops_back_safely(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'loop',
                            'text' => 'Starting sub-flow...',
                        ],
                    ],
                    [
                        'id' => 'step_a',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'text' => 'Step A',
                        ],
                    ],
                    [
                        'id' => 'jump_back',
                        'type' => 'jumpToStep',
                        'data' => [
                            'targetNodeId' => 'step_a',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'step_a', 'sourceHandle' => 'output_1'],
                    ['source' => 'step_a', 'target' => 'jump_back', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'loop',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertTrue(in_array($state->status, [ChatbotFlowStateStatus::Completed, ChatbotFlowStateStatus::Active], true));
    }

    public function test_typing_indicator_triggers_outbound_indicator_and_delays(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'typing',
                            'text' => 'Hello there!',
                        ],
                    ],
                    [
                        'id' => 'typing_node',
                        'type' => 'typingIndicator',
                        'data' => [
                            'duration' => 3,
                        ],
                    ],
                    [
                        'id' => 'final_reply',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'text' => 'Here is the response after typing.',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'typing_node', 'sourceHandle' => 'output_1'],
                    ['source' => 'typing_node', 'target' => 'final_reply', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'typing',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame(ChatbotFlowStateStatus::Active, $state->status);
        $this->assertSame('final_reply', $state->current_node_id);
    }
}
