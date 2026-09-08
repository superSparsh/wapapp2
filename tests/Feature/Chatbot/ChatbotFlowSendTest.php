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
use App\Models\Template;
use App\Models\WhatsappFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotFlowSendTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private array $interactivePayloads = [];

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
            $mock->shouldReceive('sendTemplate')->andReturn(new Message([
                'id' => 1000,
                'body' => 'template',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Template,
            ]));
            $mock->shouldReceive('sendInteractive')
                ->andReturnUsing(function ($conversation, array $content) {
                    $this->interactivePayloads[] = $content;

                    return new Message([
                        'id' => 1001,
                        'body' => (string) ($content['body']['text'] ?? 'interactive'),
                        'direction' => MessageDirection::Outbound,
                        'message_type' => MessageType::Interactive,
                    ]);
                });
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

    public function test_interactive_flow_node_sends_real_flow_payload(): void
    {
        $whatsappFlow = WhatsappFlow::factory()->active()->create([
            'meta_flow_id' => 'flow_meta_send_1',
            'meta_json' => [
                'screens' => [
                    ['id' => 'ENTRY'],
                ],
            ],
        ]);

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'flow',
                            'text' => 'Starting flow',
                        ],
                    ],
                    [
                        'id' => 'interactive_flow_1',
                        'type' => 'interactiveMessage',
                        'data' => [
                            'interactiveType' => 'flow',
                            'type' => 'flow',
                            'bodyText' => 'Complete this form',
                            'body' => ['text' => 'Complete this form'],
                            'flow_id' => 'flow_meta_send_1',
                            'flow_cta' => 'Start',
                            'flow_token' => 'flow_meta_send_1_test_token',
                            'action' => [
                                'name' => 'flow',
                                'parameters' => [
                                    'mode' => 'published',
                                    'flow_message_version' => '3',
                                    'flow_token' => 'flow_meta_send_1_test_token',
                                    'flow_id' => 'flow_meta_send_1',
                                    'flow_cta' => 'Start',
                                    'flow_action' => 'navigate',
                                    'flow_action_payload' => [
                                        'screen' => 'ENTRY',
                                        'data' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'interactive_flow_1', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'flow',
            'direction' => MessageDirection::Inbound,
        ]));

        $this->assertCount(1, $this->interactivePayloads);
        $this->assertSame('flow', $this->interactivePayloads[0]['type']);
        $this->assertSame('flow_meta_send_1', $this->interactivePayloads[0]['action']['parameters']['flow_id']);
        $this->assertSame('ENTRY', $this->interactivePayloads[0]['action']['parameters']['flow_action_payload']['screen']);

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('interactive_flow_1', $state->current_node_id);
        $this->assertSame('flow_meta_send_1', $state->variables['_whatsapp_flow_id']);
    }

    public function test_whatsapp_flow_template_node_sends_template_code(): void
    {
        $template = Template::factory()->create([
            'code' => 'flow_template_code',
            'name' => 'Flow Template',
        ]);

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'template',
                            'text' => 'Sending template',
                        ],
                    ],
                    [
                        'id' => 'flow_template_1',
                        'type' => 'whatsappFlowTemplate',
                        'data' => [
                            'templateId' => $template->id,
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'welcome_1', 'target' => 'flow_template_1', 'sourceHandle' => 'output_1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'template',
            'direction' => MessageDirection::Inbound,
        ]));

        $state = ChatbotFlowState::query()->first();
        $this->assertNotNull($state);
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('flow_template_1', $state->current_node_id);
    }
}
