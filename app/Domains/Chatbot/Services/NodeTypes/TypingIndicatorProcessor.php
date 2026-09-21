<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Jobs\ProcessDelayedNodeJob;
use App\Domains\Chatbot\Jobs\SendTypingIndicatorJob;
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

        $durationSeconds = $this->resolveDurationSeconds($data);
        $showTyping = $this->flagEnabled($data['showTyping'] ?? true);
        $repeatTyping = $this->flagEnabled($data['repeatTyping'] ?? false);
        $maxRepeats = max(1, min(10, (int) ($data['maxRepeats'] ?? 3)));

        $nextId = $this->defaultNextNodeId($node);

        if ($nextId === null) {
            return NodeProcessResult::Completed;
        }

        if ($showTyping) {
            $this->sendTypingIndicator($conversation);

            // Legacy repeatTyping: pulse the indicator while waiting.
            if ($repeatTyping && $durationSeconds > 2) {
                $pulses = min($maxRepeats, max(1, (int) floor($durationSeconds / 2)));
                $interval = max(2, (int) floor($durationSeconds / max(1, $pulses)));

                for ($i = 1; $i < $pulses; $i++) {
                    SendTypingIndicatorJob::dispatch(
                        conversationId: $conversation->id,
                    )->delay(now()->addSeconds($interval * $i))
                        ->onQueue((string) config('chatbot.delay_queue', 'chatbot'));
                }
            }
        }

        $state->forceFill(['current_node_id' => $nextId])->save();

        ProcessDelayedNodeJob::dispatch(
            conversationId: $conversation->id,
            stateId: $state->id,
            nextNodeId: $nextId,
        )->delay(now()->addSeconds($durationSeconds))
            ->onQueue((string) config('chatbot.delay_queue', 'chatbot'));

        return NodeProcessResult::Delayed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDurationSeconds(array $data): int
    {
        if (isset($data['duration']) || isset($data['durationSeconds']) || isset($data['delaySeconds']) || isset($data['delay_seconds'])) {
            $seconds = (int) ($data['duration'] ?? $data['durationSeconds'] ?? $data['delaySeconds'] ?? $data['delay_seconds'] ?? 3);
        } else {
            $seconds = match ((string) ($data['typingSpeed'] ?? 'normal')) {
                'slow' => 2,
                'fast' => 1,
                'custom' => (int) round((float) ($data['customDuration'] ?? 3)),
                default => 3,
            };
        }

        return max(1, min(60, $seconds));
    }

    private function flagEnabled(mixed $flag): bool
    {
        return $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
    }
}
