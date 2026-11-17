<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Domains\AiBot\Services\AiInboundReplyService;
use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Services\NodeTypes\FunctionCallProcessor;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Domains\Support\Chatbot\HandoffToInboxHandler;
use App\Domains\Support\Services\SupportHandoffService;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ConversationResponseType;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SupportHandoffTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_handoff_sets_human_response_completes_chatbot_and_unassigns(): void
    {
        AiSetting::set('ai_auto_response_enabled', false);

        $agent = User::factory()->create(['is_active' => true]);
        $bot = AiBot::factory()->active()->create();
        $flow = ChatbotFlow::factory()->active()->create();

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
            'active_ai_bot_id' => $bot->id,
            'assigned_user_id' => $agent->id,
            'assigned_team_member_id' => null,
        ]);

        ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'wait_1',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);

        $result = app(SupportHandoffService::class)->handoffToAgent($conversation);

        $conversation->refresh();

        $this->assertSame('inbox', $result['channel']);
        $this->assertFalse($result['already_escalated']);
        $this->assertTrue($result['message_sent']);
        $this->assertSame(ConversationResponseType::Human, $conversation->response_type);
        $this->assertNull($conversation->active_ai_bot_id);
        $this->assertNull($conversation->assigned_user_id);
        $this->assertNull($conversation->assigned_team_member_id);

        $this->assertSame(0, ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
                ChatbotFlowStateStatus::Delayed->value,
            ])
            ->count());

        $this->assertSame(
            ChatbotFlowStateStatus::Completed,
            ChatbotFlowState::query()->forConversation($conversation->id)->first()?->status,
        );
    }

    public function test_handoff_stops_ai_inbound_when_global_auto_is_off(): void
    {
        AiProviderKey::factory()->create(['is_active' => true, 'is_validated' => true]);
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', false);

        $flow = ChatbotFlow::factory()->active()->create();
        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);

        ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'nl_1',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);

        app(SupportHandoffService::class)->handoffToAgent($conversation);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Still need help',
            'direction' => MessageDirection::Inbound,
        ]);

        $this->assertFalse(
            app(AiInboundReplyService::class)->shouldTrigger($conversation->refresh(), $message),
        );
    }

    public function test_handoff_stops_ai_inbound_even_when_global_auto_is_on(): void
    {
        AiProviderKey::factory()->create(['is_active' => true, 'is_validated' => true]);
        AiBot::factory()->active()->create();
        AiSetting::set('ai_auto_response_enabled', true);

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);

        app(SupportHandoffService::class)->handoffToAgent($conversation);

        $this->assertSame(ConversationResponseType::Human, $conversation->refresh()->response_type);

        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'message_type' => MessageType::Text,
            'body' => 'Still need help',
            'direction' => MessageDirection::Inbound,
        ]);

        $ai = app(AiInboundReplyService::class);
        $this->assertFalse($ai->isEligibleForAutoReply($conversation->refresh()));
        $this->assertFalse($ai->shouldTrigger($conversation->refresh(), $message));
    }

    public function test_second_handoff_is_idempotent_and_reports_already_escalated(): void
    {
        AiSetting::set('ai_auto_response_enabled', false);

        $flow = ChatbotFlow::factory()->active()->create();
        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);

        ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'wait_1',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Waiting,
            'expires_at' => now()->addHour(),
        ]);

        $first = app(SupportHandoffService::class)->handoffToAgent($conversation);
        $second = app(SupportHandoffService::class)->handoffToAgent($conversation->refresh());

        $this->assertFalse($first['already_escalated']);
        $this->assertTrue($second['already_escalated']);
        $this->assertTrue($second['message_sent']);
        $this->assertSame(ConversationResponseType::Human, $conversation->refresh()->response_type);
    }

    public function test_chatbot_function_registry_resolves_handoff_to_inbox(): void
    {
        $this->assertSame(
            HandoffToInboxHandler::class,
            config('chatbot.function_registry.handoff_to_inbox'),
        );

        AiSetting::set('ai_auto_response_enabled', false);

        $flow = ChatbotFlow::factory()->active()->create();
        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);

        $state = ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'fn_handoff',
            'variables' => [],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addHour(),
        ]);

        $node = [
            'id' => 'fn_handoff',
            'type' => 'functionCall',
            'data' => [
                'functionName' => 'handoff_to_inbox',
                'resultVariable' => 'handoff_result',
                'parameters' => [
                    ['key' => 'confirmation_message', 'value' => 'Agent connecting shortly.'],
                ],
            ],
            'outputs' => [],
        ];

        $processor = app(FunctionCallProcessor::class);
        $result = $processor->process($node, ['fn_handoff' => $node], $conversation, $state);

        $state->refresh();
        $conversation->refresh();

        $this->assertSame(NodeProcessResult::Completed, $result);
        $this->assertSame(ConversationResponseType::Human, $conversation->response_type);
        $this->assertTrue((bool) ($state->variables['handoff_result_success'] ?? false));
        $this->assertSame('inbox', $state->variables['handoff_result']['channel'] ?? null);
        $this->assertTrue((bool) ($state->variables['handoff_result']['end_flow'] ?? false));
        $this->assertSame(
            ChatbotFlowStateStatus::Completed,
            ChatbotFlowState::query()->forConversation($conversation->id)->first()?->status,
        );
    }

    public function test_handler_can_skip_confirmation_message(): void
    {
        $outbound = Mockery::mock(InboxOutboundService::class);
        $outbound->shouldNotReceive('sendText');
        $this->app->instance(InboxOutboundService::class, $outbound);

        AiSetting::set('ai_auto_response_enabled', false);

        $conversation = Conversation::factory()->create([
            'response_type' => ConversationResponseType::Ai,
        ]);

        $result = app(HandoffToInboxHandler::class)->handle(
            ['send_confirmation' => false],
            [],
            $conversation,
        );

        $this->assertFalse($result['message_sent']);
        $this->assertSame(ConversationResponseType::Human, $conversation->refresh()->response_type);
    }
}
