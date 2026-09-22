<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Jobs\ProcessDelayedNodeJob;
use App\Enums\ChatbotFlowStateStatus;
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
        $delaySeconds = (int) ($data['delaySeconds'] ?? $data['seconds'] ?? $data['delay'] ?? 0);

        if ($delaySeconds < 1) {
            $delaySeconds = ((int) ($data['delayInHours'] ?? 0)) * 3600
                + ((int) ($data['delayInMinutes'] ?? 0)) * 60
                + ((int) ($data['delayInSeconds'] ?? 0));
        }

        if ($delaySeconds < 1) {
            $delaySeconds = 5;
        }

        // Clamp between 1 second and 7 days
        $delaySeconds = max(1, min(604800, $delaySeconds));

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId === null) {
            return NodeProcessResult::Completed;
        }

        // Point at the next node but mark Delayed so inbound cannot skip the wait.
        $state->forceFill([
            'current_node_id' => $nextId,
            'status' => ChatbotFlowStateStatus::Delayed,
        ])->save();

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
