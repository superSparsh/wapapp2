<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class ChatbotFlowStateService
{
    /**
     * Create a new flow state for a conversation.
     *
     * @param  array<string, mixed>|null  $variables
     */
    public function create(
        Conversation $conversation,
        int $flowId,
        string $startNodeId,
        ?array $variables = null,
    ): ChatbotFlowState {
        return ChatbotFlowState::query()->create([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flowId,
            'current_node_id' => $startNodeId,
            'variables' => $variables ?? [],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addMinutes((int) config('chatbot.state_ttl_minutes', 2)),
        ]);
    }

    /**
     * Find the active or waiting state for a conversation.
     */
    public function findActive(Conversation $conversation): ?ChatbotFlowState
    {
        return ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
            ])
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Expire all active/waiting states for a conversation.
     */
    public function expireAll(Conversation $conversation): int
    {
        return ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
            ])
            ->update([
                'status' => ChatbotFlowStateStatus::Expired,
                'processed_at' => now(),
            ]);
    }

    /**
     * Expire stale states across all conversations (called by scheduled task).
     */
    public function expireStaleStates(): int
    {
        return ChatbotFlowState::query()
            ->whereIn('status', [
                ChatbotFlowStateStatus::Active->value,
                ChatbotFlowStateStatus::Waiting->value,
            ])
            ->where('expires_at', '<', now())
            ->update([
                'status' => ChatbotFlowStateStatus::Expired,
                'processed_at' => now(),
            ]);
    }
}
