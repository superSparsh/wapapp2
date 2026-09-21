<?php

declare(strict_types=1);

namespace Tests\Unit\AiBot;

use App\Domains\AiBot\Services\AiBotRouterService;
use App\Domains\AiBot\Services\AiInboundReplyService;
use App\Enums\ConversationResponseType;
use App\Enums\MessageType;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AiInboundReplyServiceTest extends TestCase
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

    public function test_should_not_trigger_without_provider_key(): void
    {
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', true);

        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Hello',
        ]);

        $service = app(AiInboundReplyService::class);

        $this->assertFalse($service->shouldTrigger($conversation, $message));
    }

    public function test_should_trigger_when_global_auto_response_enabled(): void
    {
        AiProviderKey::factory()->create(['is_active' => true, 'is_validated' => true]);
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', true);

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Hello',
        ]);

        $service = app(AiInboundReplyService::class);

        $this->assertTrue($service->shouldTrigger($conversation, $message));
    }

    public function test_should_not_trigger_when_human_response_mode(): void
    {
        AiProviderKey::factory()->create(['is_active' => true, 'is_validated' => true]);
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', true);

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Human,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Hello',
        ]);

        $service = app(AiInboundReplyService::class);

        $this->assertFalse($service->shouldTrigger($conversation, $message));
    }

    public function test_should_not_trigger_when_chatbot_owns_conversation(): void
    {
        AiProviderKey::factory()->create(['is_active' => true, 'is_validated' => true]);
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', true);

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);
        \App\Models\ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => \App\Models\ChatbotFlow::factory()->create()->id,
            'current_node_id' => 'n1',
            'status' => \App\Enums\ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
            'variables' => [],
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Hello',
        ]);

        $service = app(AiInboundReplyService::class);

        $this->assertFalse($service->shouldTrigger($conversation, $message));
    }

    public function test_should_trigger_when_conversation_is_ai_mode(): void
    {
        AiProviderKey::factory()->create(['is_active' => true]);
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', false);

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Need help',
        ]);

        $service = app(AiInboundReplyService::class);

        $this->assertTrue($service->shouldTrigger($conversation, $message));
    }

    public function test_handle_routes_when_enabled(): void
    {
        AiProviderKey::factory()->create(['is_active' => true, 'is_validated' => true]);
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', true);

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Hi',
        ]);

        $router = Mockery::mock(AiBotRouterService::class);
        $router->shouldReceive('route')->once()->andReturn('Hello from AI');

        $service = new AiInboundReplyService($router);

        $this->assertTrue($service->handle($conversation, $message));
    }

    public function test_ai_setting_bool_round_trip(): void
    {
        AiSetting::set('ai_auto_response_enabled', false);
        $this->assertFalse(AiSetting::getBool('ai_auto_response_enabled', true));

        AiSetting::set('ai_auto_response_enabled', true);
        $this->assertTrue(AiSetting::getBool('ai_auto_response_enabled', false));
    }
}
