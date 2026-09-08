<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Jobs\ProcessDelayedNodeJob;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class DelayProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);
        $delaySeconds = (int) ($data['delaySeconds'] ?? $data['seconds'] ?? $data['delay'] ?? 5);

        // Clamp between 1 and 3600 seconds
        $delaySeconds = max(1, min(3600, $delaySeconds));

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId === null) {
            return NodeProcessResult::Completed;
        }

        // Update state to point at the next node (after delay)
        $state->forceFill(['current_node_id' => $nextId])->save();

        // Dispatch a delayed job to continue the flow
        ProcessDelayedNodeJob::dispatch(
            conversationId: $conversation->id,
            stateId: $state->id,
            nextNodeId: $nextId,
        )->delay(now()->addSeconds($delaySeconds))
            ->onQueue((string) config('chatbot.delay_queue', 'chatbot'));

        return NodeProcessResult::Delayed;
    }
}
