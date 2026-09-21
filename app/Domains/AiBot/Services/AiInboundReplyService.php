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

    public function handle(Conversation $conversation, Message $message): bool
    {
        if (! $this->shouldTrigger($conversation, $message)) {
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

    public function shouldTrigger(Conversation $conversation, Message $message): bool
    {
        // Media-only / system noise — AI needs text (or interactive title stored as body).
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

        // Chatbot owns this conversation (mid-flow / waiting) — never let AI steal the turn.
        if ($this->chatbotOwnsConversation($conversation)) {
            return false;
        }

        // Explicit human takeover — chatbot/AI stay out until switched back to AI.
        if ($conversation->response_type === ConversationResponseType::Human) {
            return false;
        }

        if (! $this->hasConfiguredProvider()) {
            return false;
        }

        if (! AiBot::query()->active()->exists() && $conversation->active_ai_bot_id === null) {
            return false;
        }

        // Per-conversation AI mode (legacy sub_reply.response_type = ai_response).
        if ($conversation->response_type === ConversationResponseType::Ai) {
            return true;
        }

        // Global tenant toggle (legacy customers.ai_response).
        if (AiSetting::getBool('ai_auto_response_enabled', false)) {
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
