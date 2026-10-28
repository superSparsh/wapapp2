<?php

declare(strict_types=1);

namespace Tests\Feature\Chatbot;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Domains\Chatbot\Services\ChatbotFlowService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\TriggerTemplate\Enums\TriggerFireResult;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotTemplateEntryTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        Queue::fake();

        $this->mock(InboxOutboundService::class, function ($mock): void {
            $mock->shouldReceive('sendText')->andReturnUsing(function ($conversation, string $body) {
                $this->sent[] = $body;

                return new Message([
                    'id' => 999,
                    'body' => $body,
                    'direction' => MessageDirection::Outbound,
                    'message_type' => MessageType::Text,
                ]);
            });
            $mock->shouldReceive('sendTemplate')->andReturn(new Message([
                'id' => 1000,
                'body' => 'template',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Template,
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

    public function test_interactive_reply_to_outbound_template_starts_matching_chatbot(): void
    {
        $providerCode = '1234567890123';
        $template = Template::factory()->create([
            'code' => $providerCode,
            'name' => 'Promo Entry',
        ]);

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'template_1',
                        'type' => 'templateMessage',
                        'data' => [
                            'messageType' => 'template',
                            'templateId' => $template->id,
                            'templateCode' => $providerCode,
                            'quickReplies' => [
                                ['id' => 'yes', 'text' => 'Yes'],
                                ['id' => 'no', 'text' => 'No'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'yes_branch',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'welcomeMessage' => 'You chose Yes',
                        ],
                    ],
                    [
                        'id' => 'no_branch',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'welcomeMessage' => 'You chose No',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'template_1', 'target' => 'yes_branch', 'sourceHandle' => 'reply-0'],
                    ['source' => 'template_1', 'target' => 'no_branch', 'sourceHandle' => 'reply-1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'external_message_id' => 'wamid.outbound-template-1',
            'message_type' => MessageType::Template,
            'body' => 'Promo template',
            'metadata' => [
                'template_code' => $providerCode,
                'template_id' => $template->id,
            ],
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Yes',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'metadata' => [
                'context_message_id' => 'wamid.outbound-template-1',
                'interactive_reply_id' => 'yes',
                'interactive_reply_type' => 'button_reply',
            ],
        ]));

        $this->assertSame(TriggerFireResult::Fired, $result);
        $this->assertContains('You chose Yes', $this->sent);
        $this->assertNotContains('You chose No', $this->sent);
        $this->assertSame(1, ChatbotFlowState::query()->count());
    }

    public function test_interactive_reply_without_matching_chatbot_is_no_match(): void
    {
        $conversation = Conversation::factory()->create();

        Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'external_message_id' => 'wamid.orphan-template',
            'message_type' => MessageType::Template,
            'metadata' => [
                'template_code' => '9999999999999',
                'template_id' => 999999,
            ],
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Yes',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'metadata' => [
                'context_message_id' => 'wamid.orphan-template',
                'interactive_reply_id' => 'yes',
                'interactive_reply_type' => 'button_reply',
            ],
        ]));

        $this->assertSame(TriggerFireResult::NoMatch, $result);
        $this->assertSame(0, ChatbotFlowState::query()->count());
    }

    public function test_welcome_keyword_still_wins_over_template_context(): void
    {
        $providerCode = '1234567890456';
        $template = Template::factory()->create([
            'code' => $providerCode,
            'name' => 'Both Paths',
        ]);

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'hello',
                            'welcomeMessage' => 'Keyword path',
                        ],
                    ],
                    [
                        'id' => 'template_1',
                        'type' => 'templateMessage',
                        'data' => [
                            'messageType' => 'template',
                            'templateId' => $template->id,
                            'templateCode' => $providerCode,
                            'quickReplies' => [['id' => 'yes', 'text' => 'Yes']],
                        ],
                    ],
                    [
                        'id' => 'tpl_branch',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'welcomeMessage' => 'Template path',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'template_1', 'target' => 'tpl_branch', 'sourceHandle' => 'reply-0'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'external_message_id' => 'wamid.with-context',
            'message_type' => MessageType::Template,
            'metadata' => [
                'template_code' => $providerCode,
                'template_id' => $template->id,
            ],
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'metadata' => [
                'context_message_id' => 'wamid.with-context',
                'interactive_reply_id' => 'yes',
                'interactive_reply_type' => 'button_reply',
            ],
        ]));

        $this->assertSame(TriggerFireResult::Fired, $result);
        $this->assertContains('Keyword path', $this->sent);
        $this->assertNotContains('Template path', $this->sent);
    }

    public function test_activation_allows_template_only_flow(): void
    {
        $providerCode = '1234567890789';
        $template = Template::factory()->create([
            'code' => $providerCode,
            'name' => 'Entry Only',
        ]);

        $flow = ChatbotFlow::factory()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'template_1',
                        'type' => 'templateMessage',
                        'data' => [
                            'messageType' => 'template',
                            'templateId' => $template->id,
                            'templateCode' => $providerCode,
                            'quickReplies' => [['text' => 'Yes']],
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $service = app(ChatbotFlowService::class);

        $this->assertSame([], $service->activationErrors($flow));
        $this->assertTrue($service->hasTemplateEntry($flow));
    }

    public function test_activation_errors_when_neither_keyword_nor_template(): void
    {
        $flow = ChatbotFlow::factory()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'media_1',
                        'type' => 'mediaMessage',
                        'data' => [
                            'media_url' => 'https://example.com/a.jpg',
                        ],
                    ],
                ],
                'edges' => [],
            ],
        ]);

        $errors = app(ChatbotFlowService::class)->activationErrors($flow);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('welcome message', $errors[0]);
    }

    public function test_inbox_template_reply_works_without_context_message_id(): void
    {
        $this->assertTemplateEntryFromOutboundSource(
            providerCode: '1234567890444',
            walletSource: 'inbox',
            expectedText: 'Thanks from inbox path',
        );
    }

    public function test_campaign_template_reply_starts_matching_chatbot(): void
    {
        $this->assertTemplateEntryFromOutboundSource(
            providerCode: '1234567890555',
            walletSource: 'campaign',
            expectedText: 'Thanks from campaign path',
        );
    }

    private function assertTemplateEntryFromOutboundSource(
        string $providerCode,
        string $walletSource,
        string $expectedText,
    ): void {
        $template = Template::factory()->create([
            'code' => $providerCode,
            'name' => ucfirst($walletSource).' Promo',
        ]);

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'template_1',
                        'type' => 'templateMessage',
                        'data' => [
                            'messageType' => 'template',
                            'templateId' => $template->id,
                            'templateCode' => $providerCode,
                            'quickReplies' => [
                                ['text' => 'Plain CHX'],
                                ['text' => 'CHX + Vit C'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'thanks_1',
                        'type' => 'templateMessage',
                        'data' => [
                            'messageType' => 'custom',
                            'customText' => $expectedText,
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'template_1', 'target' => 'thanks_1', 'sourceHandle' => 'reply-0'],
                    ['source' => 'template_1', 'target' => 'thanks_1', 'sourceHandle' => 'reply-1'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'external_message_id' => 'wamid.'.$walletSource.'-template',
            'message_type' => MessageType::Template,
            'metadata' => [
                'template_code' => $providerCode,
                'template_id' => $template->id,
                'wallet_source' => $walletSource,
                'campaign_id' => $walletSource === 'campaign' ? 99 : null,
            ],
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Plain CHX',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'metadata' => [
                // Campaign/Inbox button webhooks may omit context ids.
                'interactive_reply_id' => 'Plain CHX',
                'interactive_reply_type' => 'button',
            ],
        ]));

        $this->assertSame(TriggerFireResult::Fired, $result);
        $this->assertContains($expectedText, $this->sent);
    }

    public function test_all_quick_reply_branches_to_same_node_still_continues(): void
    {
        $providerCode = '1234567890333';
        $template = Template::factory()->create([
            'code' => $providerCode,
            'name' => 'CHX Feedback',
        ]);

        ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'template_1',
                        'type' => 'templateMessage',
                        'data' => [
                            'messageType' => 'template',
                            'templateId' => $template->id,
                            'templateCode' => $providerCode,
                            'quickReplies' => [
                                ['text' => 'Plain CHX'],
                                ['text' => 'CHX + Vit C'],
                                ['text' => 'CHX + Fluoride'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'thanks_1',
                        'type' => 'templateMessage',
                        'data' => [
                            'messageType' => 'custom',
                            'customText' => 'Thank you for sharing your feedback',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'template_1', 'target' => 'thanks_1', 'sourceHandle' => 'reply-0'],
                    ['source' => 'template_1', 'target' => 'thanks_1', 'sourceHandle' => 'reply-1'],
                    ['source' => 'template_1', 'target' => 'thanks_1', 'sourceHandle' => 'reply-2'],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();

        Message::factory()->outbound()->create([
            'conversation_id' => $conversation->id,
            'external_message_id' => 'wamid.chx-template',
            'message_type' => MessageType::Template,
            'metadata' => [
                'template_code' => $providerCode,
                'template_id' => $template->id,
            ],
        ]);

        $engine = app(ChatbotFlowEngine::class);
        // Payload/title mismatch vs stored QR labels - shared-target fallback must still continue.
        $result = $engine->processInbound($conversation, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Option 2',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Interactive,
            'metadata' => [
                'context_message_id' => 'wamid.chx-template',
                'interactive_reply_id' => 'btn_payload_2',
                'interactive_reply_type' => 'button_reply',
            ],
        ]));

        $this->assertSame(TriggerFireResult::Fired, $result);
        $this->assertContains('Thank you for sharing your feedback', $this->sent);
    }
}
