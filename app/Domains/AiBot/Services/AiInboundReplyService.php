<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Services;

use App\Enums\ConversationResponseType;
use App\Enums\MessageType;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\AiBot;
use App\Models\AiProviderKey;
use App\Models\AiSetting;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether an inbound WhatsApp message should get an AI reply
 * (legacy parity: customer ai_response + conversation ai_response + configured key/bots).
 */
class AiInboundReplyService
{
    public function __construct(
        private readonly AiBotRouterService $router,
    ) {}

    public function handle(
        Conversation $conversation,
        Message $message,
        bool $ignoreChatbotOwnership = false,
    ): bool {
        if (! $this->shouldTrigger($conversation, $message, $ignoreChatbotOwnership)) {
            return false;
        }

        $userMessage = trim((string) ($message->body ?? ''));
        if ($userMessage === '') {
            return false;
        }

        try {
            $reply = $this->router->route($conversation->refresh(), $userMessage);

            return is_string($reply) && $reply !== '';
        } catch (\Throwable $e) {
            Log::warning('AI inbound reply failed', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function shouldTrigger(
        Conversation $conversation,
        Message $message,
        bool $ignoreChatbotOwnership = false,
    ): bool {
        // Media-only / system noise - AI needs text (or interactive title stored as body).
        if (! in_array($message->message_type, [
            MessageType::Text,
            MessageType::Interactive,
            MessageType::Template,
        ], true)) {
            $body = trim((string) ($message->body ?? ''));
            if ($body === '') {
                return false;
            }
        }

        // Chatbot keyword flows / mid-flow waiting own the turn - never let AI steal
        // unless this inbound already got an offline-hours (FiredAllowAi) chatbot reply.
        if (! $ignoreChatbotOwnership && $this->chatbotOwnsConversation($conversation)) {
            return false;
        }

        return $this->isEligibleForAutoReply($conversation);
    }

    /**
     * Whether AI would answer this chat if chatbot does not own the turn.
     * Used by chatbot offline-hours deferral so a canned offline reply does not
     * block a configured AI Assistant outside business hours.
     */
    public function isEligibleForAutoReply(Conversation $conversation): bool
    {
        if (! $this->hasConfiguredProvider()) {
            return false;
        }

        if ($conversation->active_ai_bot_id === null) {
            $lineId = $conversation->whatsapp_line_id !== null
                ? (int) $conversation->whatsapp_line_id
                : null;
            $botsQuery = AiBot::query()->active();
            if ($lineId !== null) {
                $botsQuery->where(function ($q) use ($lineId): void {
                    $q->where('whatsapp_line_id', $lineId)->orWhereNull('whatsapp_line_id');
                });
            }
            if (! $botsQuery->exists()) {
                return false;
            }
        }

        $globalAuto = AiSetting::getBool('ai_auto_response_enabled', false);

        // Inbox / handoff "AI off" is authoritative — even when tenant global auto is on.
        // New chats already start as ai_response when global auto is enabled.
        if ($conversation->response_type === ConversationResponseType::Human) {
            return false;
        }

        // Per-conversation AI mode (legacy sub_reply.response_type = ai_response).
        if ($conversation->response_type === ConversationResponseType::Ai) {
            return true;
        }

        // Global tenant toggle (legacy customers.ai_response) for chats not explicitly human.
        if ($globalAuto) {
            return true;
        }

        // Sticky bot already assigned to this chat.
        if ($conversation->active_ai_bot_id !== null) {
            $bot = AiBot::query()->find($conversation->active_ai_bot_id);

            return $bot !== null && $bot->isActive();
        }

        return false;
    }

    private function chatbotOwnsConversation(Conversation $conversation): bool
    {
        return ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
                ChatbotFlowStateStatus::Delayed->value,
            ])
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function hasConfiguredProvider(): bool
    {
        return AiProviderKey::query()
            ->where('is_active', true)
            ->where(function ($q): void {
                $q->where('is_validated', true)
                    ->orWhereNotNull('api_key');
            })
            ->exists();
    }
}
