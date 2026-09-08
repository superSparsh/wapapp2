<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Jobs\ProcessDelayedNodeJob;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

class TypingIndicatorProcessor extends AbstractNodeProcessor
{
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult {
        $data = $this->nodeData($node);

        $durationSeconds = (int) ($data['duration'] ?? $data['durationSeconds'] ?? 3);
        $durationSeconds = max(1, min(25, $durationSeconds));

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId === null) {
            return NodeProcessResult::Completed;
        }

        // Trigger real WhatsApp typing indicator via CAMS
        $this->sendTypingIndicator($conversation);

        // Update state to point at next node
        $state->forceFill(['current_node_id' => $nextId])->save();

        // Dispatch delayed job to continue after the typing duration
        ProcessDelayedNodeJob::dispatch(
            conversationId: $conversation->id,
            stateId: $state->id,
            nextNodeId: $nextId,
        )->delay(now()->addSeconds($durationSeconds))
            ->onQueue((string) config('chatbot.delay_queue', 'chatbot'));

        return NodeProcessResult::Delayed;
    }
}
