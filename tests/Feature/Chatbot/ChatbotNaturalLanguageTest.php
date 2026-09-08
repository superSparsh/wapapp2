<?php

namespace Tests\Feature\Chatbot;

use App\Domains\AiBot\Services\AiChatService;
use App\Domains\AiBot\Services\AiProviderKeyService;
use App\Domains\AiBot\Services\Providers\AiProviderInterface;
use App\Domains\Billing\Services\WalletService;
use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\AiProvider;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotNaturalLanguageTest extends TestCase
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
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_natural_language_node_executes_ai_knowledge_base_mode(): void
    {
        $bot = AiBot::factory()->create([
            'name' => 'Support Assistant',
            'provider' => AiProvider::OpenAI,
            'chat_model' => 'gpt-4o-mini',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->mock(AiChatService::class, function ($mock) use ($bot): void {
            $mock->shouldReceive('processMessage')
                ->once()
                ->andReturn('Hello! How can I help you today with our services?');
        });

        $flow = ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'ai_help',
                            'text' => 'Connecting to AI...',
                        ],
                    ],
                    [
                        'id' => 'nl_1',
                        'type' => 'naturalLanguage',
                        'data' => [
                            'label' => 'AI Support Assistant',
                            'mode' => 'knowledge_base',
                            'ai_bot_id' => $bot->id,
                            'sendDirectly' => true,
                            'outputVariable' => '_ai_response',
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e1',
                        'source' => 'welcome_1',
                        'target' => 'nl_1',
                        'sourceHandle' => 'default',
                    ],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'ai_help',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $engine->processInbound($conversation, $message);

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame('Hello! How can I help you today with our services?', $state->variables['_ai_response'] ?? null);
    }

    public function test_natural_language_node_classifies_intent_and_branches(): void
    {
        $flow = ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'pricing inquiry',
                            'text' => 'Checking request...',
                        ],
                    ],
                    [
                        'id' => 'nl_intent_1',
                        'type' => 'naturalLanguage',
                        'data' => [
                            'label' => 'Classify Customer Request',
                            'mode' => 'intent_classification',
                            'intents' => ['sales', 'support', 'pricing'],
                        ],
                    ],
                    [
                        'id' => 'pricing_step',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'text' => 'Connecting you with our pricing team!',
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e0',
                        'source' => 'welcome_1',
                        'target' => 'nl_intent_1',
                        'sourceHandle' => 'default',
                    ],
                    [
                        'id' => 'e1',
                        'source' => 'nl_intent_1',
                        'sourceHandle' => 'output_intent_pricing',
                        'target' => 'pricing_step',
                    ],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $engine = app(ChatbotFlowEngine::class);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'pricing inquiry',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine->processInbound($conversation, $message);

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame('pricing', $state->variables['_ai_intent'] ?? null);
        $this->assertSame('pricing_step', $state->current_node_id);
    }

    public function test_natural_language_generates_ai_response_without_sending_directly(): void
    {
        AiProviderKey::factory()->create([
            'provider' => AiProvider::OpenAI,
            'api_key' => 'sk-test-key-12345',
            'is_active' => true,
        ]);

        $bot = AiBot::factory()->create([
            'name' => 'Offline AI Bot',
            'provider' => AiProvider::OpenAI,
            'chat_model' => 'gpt-4o-mini',
            'status' => 'active',
            'is_default' => true,
        ]);

        $providerMock = $this->mock(AiProviderInterface::class);
        $providerMock->shouldReceive('chat')
            ->once()
            ->andReturn([
                'text' => 'Here is a draft response for your order.',
                'prompt_tokens' => 10,
                'completion_tokens' => 12,
                'total_tokens' => 22,
            ]);

        $this->mock(AiProviderKeyService::class, function ($mock) use ($providerMock): void {
            $mock->shouldReceive('resolveProvider')->andReturn($providerMock);
            $mock->shouldReceive('resolveApiKey')->andReturn('sk-mocked-key');
        });

        $flow = ChatbotFlow::factory()->active()->create([
            'exported_data' => [
                'nodes' => [
                    [
                        'id' => 'welcome_1',
                        'type' => 'welcomeMessage',
                        'data' => [
                            'messageType' => 'text',
                            'triggerKeyword' => 'offline_ai',
                            'text' => 'Starting flow...',
                        ],
                    ],
                    [
                        'id' => 'nl_variable',
                        'type' => 'naturalLanguage',
                        'data' => [
                            'label' => 'Generate Draft',
                            'mode' => 'knowledge_base',
                            'ai_bot_id' => $bot->id,
                            'sendDirectly' => false,
                            'outputVariable' => 'draft_ai_text',
                        ],
                    ],
                ],
                'edges' => [
                    [
                        'id' => 'e1',
                        'source' => 'welcome_1',
                        'target' => 'nl_variable',
                        'sourceHandle' => 'default',
                    ],
                ],
            ],
        ]);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'body' => 'offline_ai',
            'direction' => MessageDirection::Inbound,
        ]);

        $engine = app(ChatbotFlowEngine::class);
        $engine->processInbound($conversation, $message);

        $state = ChatbotFlowState::forConversation($conversation->id)->first();
        $this->assertNotNull($state);
        $this->assertSame('Here is a draft response for your order.', $state->variables['draft_ai_text'] ?? null);
    }

    public function test_builder_data_endpoint_includes_ai_bots_list(): void
    {
        AiBot::factory()->create([
            'name' => 'Demo AI Bot',
            'provider' => AiProvider::OpenAI,
            'chat_model' => 'gpt-4o',
            'status' => 'active',
            'is_default' => true,
        ]);

        $flow = ChatbotFlow::factory()->create();

        $response = $this->actingAsTenantUser()
            ->getJson(route('chatbot.builder-data', $flow))
            ->assertOk()
            ->assertJsonStructure([
                'templates',
                'interactiveMessages',
                'automationBot',
                'aiBots',
            ]);

        $aiBots = $response->json('aiBots');
        $this->assertNotEmpty($aiBots);
        $this->assertSame('Demo AI Bot', $aiBots[0]['name']);
    }
}
