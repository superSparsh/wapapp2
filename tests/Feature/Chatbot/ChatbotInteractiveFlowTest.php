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
            $mock->shouldReceive('sendInteractive')->andReturn(new Message([
                'id' => 998,
                'body' => 'mocked interactive',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Interactive,
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

        // Only one flow state should be created (newest updated bot wins)
        $this->assertSame(1, ChatbotFlowState::query()->count());
        $state = ChatbotFlowState::query()->first();
        $this->assertSame('Second Bot', ChatbotFlow::query()->find($state->chatbot_flow_id)?->name);
    }

    public function test_newer_bot_wins_over_older_bot_with_same_keyword(): void
    {
        $older = ChatbotFlow::factory()->active()->create([
            'name' => 'Older Bot',
            'updated_at' => now()->subDay(),
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_old',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'promo',
                        'welcomeMessage' => 'Old promo',
                    ],
                ]],
                'edges' => [],
            ],
        ]);
        // Force older timestamp (Eloquent may overwrite on create)
        ChatbotFlow::query()->whereKey($older->id)->update(['updated_at' => now()->subDay()]);

        $newer = ChatbotFlow::factory()->active()->create([
            'name' => 'Newer Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_new',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'promo',
                        'welcomeMessage' => 'New promo',
                    ],
                ]],
                'edges' => [],
            ],
        ]);
        ChatbotFlow::query()->whereKey($newer->id)->update(['updated_at' => now()]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'promo',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame($newer->id, $state->chatbot_flow_id);
    }

    public function test_line_specific_bot_beats_global_bot_for_same_keyword(): void
    {
        $conversation = Conversation::factory()->create();
        $lineId = (int) $conversation->whatsapp_line_id;

        ChatbotFlow::factory()->active()->create([
            'name' => 'Global Bot',
            'whatsapp_line_id' => null,
            'updated_at' => now(),
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_g',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'offers',
                        'welcomeMessage' => 'Global',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $lineBot = ChatbotFlow::factory()->active()->create([
            'name' => 'Line Bot',
            'whatsapp_line_id' => $lineId,
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_l',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'offers',
                        'welcomeMessage' => 'Line specific',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'offers',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame($lineBot->id, $state->chatbot_flow_id);
    }

    public function test_trigger_keyword_preempts_waiting_state_from_other_bot(): void
    {
        $waitingBot = ChatbotFlow::factory()->active()->create([
            'name' => 'Waiting Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'wait_welcome',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'waitbot',
                        'welcomeMessage' => 'Waiting bot',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $targetBot = ChatbotFlow::factory()->active()->create([
            'name' => 'Target Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'target_welcome',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'go',
                        'welcomeMessage' => 'Target bot',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $waitingBot->id,
            'current_node_id' => 'wait_welcome',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'go',
            'direction' => MessageDirection::Inbound,
        ]));

        $activeStates = ChatbotFlowState::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
                ChatbotFlowStateStatus::Completed->value,
            ])
            ->get();

        $this->assertTrue(
            $activeStates->contains(fn ($s) => (int) $s->chatbot_flow_id === (int) $targetBot->id)
        );
        $this->assertSame(
            ChatbotFlowStateStatus::Expired,
            ChatbotFlowState::query()->where('chatbot_flow_id', $waitingBot->id)->first()?->status
        );
    }

    public function test_exact_keyword_beats_shorter_whole_word_match(): void
    {
        ChatbotFlow::factory()->active()->create([
            'name' => 'Short Keyword Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_short',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'help',
                        'welcomeMessage' => 'Short',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $exactBot = ChatbotFlow::factory()->active()->create([
            'name' => 'Exact Phrase Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_exact',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'need help',
                        'welcomeMessage' => 'Exact',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'need help',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame($exactBot->id, $state->chatbot_flow_id);
    }

    public function test_fuzzy_keyword_does_not_steal_unrelated_button_reply(): void
    {
        $waitingBot = ChatbotFlow::factory()->active()->create([
            'name' => 'Waiting Bot',
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'interactive_1',
                        'type' => 'interactiveMessage',
                        'data' => [
                            'interactiveType' => 'button',
                            'bodyText' => 'Choose',
                            'buttons' => [
                                ['id' => 'a', 'title' => 'Alpha'],
                                ['id' => 'b', 'title' => 'Beta'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'done_a',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'welcomeMessage' => 'Got A'],
                    ],
                ],
                'edges' => [
                    ['source' => 'interactive_1', 'target' => 'done_a', 'sourceHandle' => 'button-0'],
                ],
            ],
        ]);

        // Unrelated keyword must not steal a button reply that does not contain it.
        ChatbotFlow::factory()->active()->create([
            'name' => 'Fuzzy Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_fuzzy',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'promo',
                        'welcomeMessage' => 'Fuzzy stole it',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $waitingBot->id,
            'current_node_id' => 'interactive_1',
            'variables' => [
                '_interactive_options' => [
                    ['id' => 'a', 'title' => 'Alpha'],
                    ['id' => 'b', 'title' => 'Beta'],
                ],
            ],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Alpha',
            'direction' => MessageDirection::Inbound,
        ]));

        $waitingState = ChatbotFlowState::query()
            ->where('chatbot_flow_id', $waitingBot->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($waitingState);
        $this->assertNotSame(ChatbotFlowStateStatus::Expired, $waitingState->status);
        $this->assertSame(0, ChatbotFlowState::query()->whereHas('chatbotFlow', fn ($q) => $q->where('name', 'Fuzzy Bot'))->count());
    }

    public function test_each_exact_trigger_starts_its_own_bot(): void
    {
        $sales = ChatbotFlow::factory()->active()->create([
            'name' => 'Sales Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_sales',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'sales',
                        'welcomeMessage' => 'Sales desk',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $support = ChatbotFlow::factory()->active()->create([
            'name' => 'Support Bot',
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_support',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        'triggerKeyword' => 'support',
                        'welcomeMessage' => 'Support desk',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'sales',
            'direction' => MessageDirection::Inbound,
        ]));
        $this->assertSame($sales->id, ChatbotFlowState::query()->latest('id')->first()?->chatbot_flow_id);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'support',
            'direction' => MessageDirection::Inbound,
        ]));
        $this->assertSame($support->id, ChatbotFlowState::query()->latest('id')->first()?->chatbot_flow_id);
    }

    public function test_welcome_does_not_auto_follow_reply_branch_on_trigger(): void
    {
        $sent = [];
        $this->mock(InboxOutboundService::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('sendText')->andReturnUsing(function ($conversation, string $body) use (&$sent) {
                $sent[] = $body;

                return new Message([
                    'id' => count($sent),
                    'body' => $body,
                    'direction' => MessageDirection::Outbound,
                    'message_type' => MessageType::Text,
                ]);
            });
            $mock->shouldReceive('sendInteractive')->andReturn(new Message([
                'id' => 998,
                'body' => 'interactive',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Interactive,
            ]));
            $mock->shouldReceive('sendMedia')->andReturn(new Message([
                'id' => 997,
                'body' => 'media',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Image,
            ]));
            $mock->shouldReceive('sendTypingIndicator')->andReturn(true);
        });

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hello',
                            'welcomeMessage' => 'Welcome body',
                            'quickReplies' => [
                                ['id' => 1, 'text' => 'Sales'],
                                ['id' => 2, 'text' => 'Support'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'sales_node',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'welcomeMessage' => 'WRONG sales branch'],
                    ],
                    [
                        'id' => 'next_default',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'welcomeMessage' => 'Default continue'],
                    ],
                ],
                'edges' => [
                    // Branch handle only — must NOT auto-follow on trigger
                    ['source' => 'welcome_1', 'target' => 'sales_node', 'sourceHandle' => 'reply-0'],
                    ['source' => 'welcome_1', 'target' => 'next_default', 'sourceHandle' => 'default'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertContains('Welcome body', $sent);
        $this->assertNotContains('WRONG sales branch', $sent);
        $this->assertNotContains('Default continue', $sent);

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('welcome_1', $state->current_node_id);
    }

    public function test_legacy_text_field_used_as_trigger_when_keyword_missing(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [[
                    'id' => 'welcome_1',
                    'type' => 'welcomeMessage',
                    'data' => [
                        'messageType' => 'text',
                        // Legacy Drawflow stored keyword in `text` when triggerKeyword was empty.
                        'text' => 'bookdemo',
                    ],
                ]],
                'edges' => [],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'bookdemo',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame('fired', $result->value);
        $this->assertSame(1, ChatbotFlowState::query()->count());
    }

    public function test_react_button_handle_routes_by_reply_id(): void
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
                            'welcomeMessage' => 'Welcome! Pick an option.',
                            'text' => 'menu',
                        ],
                    ],
                    [
                        'id' => 'interactive_1',
                        'type' => 'interactiveMessage',
                        'data' => [
                            'interactiveType' => 'button',
                            'bodyText' => 'Choose:',
                            'buttons' => [
                                ['id' => 'btn_sales', 'title' => 'Sales'],
                                ['id' => 'btn_support', 'title' => 'Support'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'sales_node',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'welcomeMessage' => 'Sales desk',
                        ],
                    ],
                    [
                        'id' => 'support_node',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'welcomeMessage' => 'Support desk',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'interactive_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'interactive_1', 'target' => 'sales_node', 'sourceHandle' => 'button-0'],
                    ['source' => 'interactive_1', 'target' => 'support_node', 'sourceHandle' => 'button-1'],
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

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame('interactive_1', $state->current_node_id);
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Sales',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'metadata' => [
                'interactive_reply_id' => 'btn_sales',
                'reply_id' => 'btn_sales',
            ],
        ]));

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
    }

    public function test_list_reply_routes_via_interactive_section_row_handle(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'list',
                            'welcomeMessage' => 'Opening list',
                        ],
                    ],
                    [
                        'id' => 'list_1',
                        'type' => 'interactiveMessage',
                        'data' => [
                            'interactiveType' => 'list',
                            'bodyText' => 'Pick a row',
                            'sections' => [
                                [
                                    'title' => 'Main',
                                    'rows' => [
                                        ['id' => 'row_a', 'title' => 'Option A'],
                                        ['id' => 'row_b', 'title' => 'Option B'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'node_a',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'welcomeMessage' => 'Got A'],
                    ],
                    [
                        'id' => 'node_b',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'welcomeMessage' => 'Got B'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'list_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'list_1', 'target' => 'node_a', 'sourceHandle' => 'interactive-0-0'],
                    ['source' => 'list_1', 'target' => 'node_b', 'sourceHandle' => 'interactive-0-1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'list',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::query()->firstOrFail();
        $this->assertSame('list_1', $state->current_node_id);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Option B',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'metadata' => ['interactive_reply_id' => 'row_b'],
        ]));

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
    }

    public function test_keyword_does_not_match_substring_inside_word(): void
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
                            'welcomeMessage' => 'Hello there',
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
            'body' => 'this',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame(0, ChatbotFlowState::query()->count());

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hi!',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame(1, ChatbotFlowState::query()->count());
    }

    public function test_unmatched_text_on_interactive_wait_does_not_auto_advance(): void
    {
        $sent = [];
        $this->mock(InboxOutboundService::class, function ($mock) use (&$sent): void {
            $mock->shouldReceive('sendText')->andReturnUsing(function ($conversation, string $body) use (&$sent) {
                $sent[] = $body;

                return new Message([
                    'id' => count($sent),
                    'body' => $body,
                    'direction' => MessageDirection::Outbound,
                    'message_type' => MessageType::Text,
                ]);
            });
            $mock->shouldReceive('sendInteractive')->andReturnUsing(function ($conversation, array $content) use (&$sent) {
                $sent[] = (string) ($content['body']['text'] ?? 'interactive');

                return new Message([
                    'id' => 900 + count($sent),
                    'body' => $sent[count($sent) - 1],
                    'direction' => MessageDirection::Outbound,
                    'message_type' => MessageType::Interactive,
                ]);
            });
            $mock->shouldReceive('sendMedia')->andReturn(new Message([
                'id' => 997,
                'body' => 'media',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Image,
            ]));
            $mock->shouldReceive('sendTypingIndicator')->andReturn(true);
        });

        $flow = ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'interactive_1',
                        'type' => 'interactiveMessage',
                        'data' => [
                            'interactiveType' => 'button',
                            'bodyText' => 'Choose',
                            'buttons' => [
                                ['id' => 'a', 'title' => 'Alpha'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'wrong_next',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'welcomeMessage' => 'SHOULD NOT SEND'],
                    ],
                ],
                'edges' => [
                    ['source' => 'interactive_1', 'target' => 'wrong_next', 'sourceHandle' => 'default'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'interactive_1',
            'variables' => [
                '_interactive_options' => [
                    ['id' => 'a', 'title' => 'Alpha'],
                ],
            ],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'pikaboo',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame('no_match', $result->value);
        $this->assertNotContains('SHOULD NOT SEND', $sent);

        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('interactive_1', $state->current_node_id);
    }
}
