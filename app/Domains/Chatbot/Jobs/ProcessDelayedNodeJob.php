<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Jobs;

use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessDelayedNodeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $stateId,
        public readonly string $nextNodeId,
    ) {}

    public function handle(ChatbotFlowEngine $engine): void
    {
        $state = ChatbotFlowState::query()->find($this->stateId);

        if ($state === null || $state->status->isTerminal()) {
            return;
        }

        // Only the delay job should resume a Delayed state. Also allow Active for
        // any jobs still in-flight from before Delayed status existed.
        if (! in_array($state->status, [
            ChatbotFlowStateStatus::Delayed,
            ChatbotFlowStateStatus::Active,
        ], true)) {
            return;
        }

        // Re-activate the state so the engine can continue
        $state->forceFill([
            'status' => ChatbotFlowStateStatus::Active,
            'current_node_id' => $this->nextNodeId,
        ])->save();

        $conversation = Conversation::query()->find($this->conversationId);

        if ($conversation === null) {
            return;
        }

        $engine->continueFromState($conversation, $state);
    }
}
