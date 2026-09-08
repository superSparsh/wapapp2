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
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotConditionFlowTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->mock(InboxOutboundService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->andReturn(new Message([
                'id' => 999,
                'body' => 'mocked',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Text,
            ]));
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

    public function test_condition_branches_to_yes_when_equals_matches(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'quiz',
                            'text' => 'Type yes to continue:',
                        ],
                    ],
                    [
                        'id' => 'wait_1',
                        'type' => 'waitForResponse',
                        'data' => ['variableName' => 'answer'],
                    ],
                    [
                        'id' => 'cond_1',
                        'type' => 'condition',
                        'data' => [
                            'field' => 'answer',
                            'operator' => 'equals',
                            'value' => 'yes',
                        ],
                    ],
                    [
                        'id' => 'thanks_yes',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Thanks for saying yes!'],
                    ],
                    [
                        'id' => 'thanks_no',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Sorry to hear that.'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'wait_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'wait_1', 'target' => 'cond_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'cond_1', 'target' => 'thanks_yes', 'sourceHandle' => 'output_yes'],
                    ['source' => 'cond_1', 'target' => 'thanks_no', 'sourceHandle' => 'output_no'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        // Trigger flow → pauses at wait_1
        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'quiz',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame(ChatbotFlowStateStatus::Waiting, ChatbotFlowState::query()->first()->status);

        // Reply "yes" → condition matches → flows to thanks_yes → completes
        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'yes',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame('fired', $result->value);
        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
        $this->assertSame('yes', $state->variables['answer']);
    }

    public function test_condition_branches_to_no_when_no_match(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'quiz',
                            'text' => 'Type yes:',
                        ],
                    ],
                    [
                        'id' => 'wait_1',
                        'type' => 'waitForResponse',
                        'data' => ['variableName' => 'answer'],
                    ],
                    [
                        'id' => 'cond_1',
                        'type' => 'condition',
                        'data' => [
                            'field' => 'answer',
                            'operator' => 'equals',
                            'value' => 'yes',
                        ],
                    ],
                    [
                        'id' => 'thanks_yes',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Yes branch'],
                    ],
                    [
                        'id' => 'thanks_no',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'No branch'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'wait_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'wait_1', 'target' => 'cond_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'cond_1', 'target' => 'thanks_yes', 'sourceHandle' => 'output_yes'],
                    ['source' => 'cond_1', 'target' => 'thanks_no', 'sourceHandle' => 'output_no'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'quiz',
            'direction' => MessageDirection::Inbound,
        ]));

        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'no',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertSame('fired', $result->value);
        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
        $this->assertSame('no', $state->variables['answer']);
    }

    public function test_condition_with_contains_operator(): void
    {
        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'support',
                            'text' => 'Describe your issue:',
                        ],
                    ],
                    [
                        'id' => 'wait_1',
                        'type' => 'waitForResponse',
                        'data' => ['variableName' => 'issue'],
                    ],
                    [
                        'id' => 'cond_1',
                        'type' => 'condition',
                        'data' => [
                            'field' => 'issue',
                            'operator' => 'contains',
                            'value' => 'billing',
                        ],
                    ],
                    [
                        'id' => 'billing_response',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'Billing help here.'],
                    ],
                    [
                        'id' => 'general_response',
                        'type' => 'welcomeMessage',
                        'data' => ['messageType' => 'text', 'text' => 'General help here.'],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'wait_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'wait_1', 'target' => 'cond_1', 'sourceHandle' => 'output_1'],
                    ['source' => 'cond_1', 'target' => 'billing_response', 'sourceHandle' => 'output_yes'],
                    ['source' => 'cond_1', 'target' => 'general_response', 'sourceHandle' => 'output_no'],
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

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'I have a billing question',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::query()->first();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
        $this->assertSame('I have a billing question', $state->variables['issue']);
    }
}
