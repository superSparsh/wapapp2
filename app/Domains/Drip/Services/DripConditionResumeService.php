<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\Conversation;
use App\Models\DripCampaignState;
use Illuminate\Support\Facades\Log;

/**
 * When WhatsApp delivery/read/failed status or an inbound reply arrives,
 * wake drip states that are waiting on a condition evaluation window so
 * they can take the Yes/No branch early - without cutting delay waits short.
 */
class DripConditionResumeService
{
    public function resumeForConversation(Conversation $conversation): int
    {
        $states = DripCampaignState::query()
            ->where('conversation_id', $conversation->id)
            ->where('status', ChatbotFlowStateStatus::Waiting)
            ->orderBy('id')
            ->get();

        if ($states->isEmpty()) {
            return 0;
        }

        $resumed = 0;

        foreach ($states as $state) {
            if (! $this->isWaitingOnCondition($state)) {
                continue;
            }

            try {
                ExecuteDripStepJob::dispatch($state->id)
                    ->onQueue((string) config('chatbot.drip.queue', 'default'));
                $resumed++;
            } catch (\Throwable $e) {
                Log::warning('drip.condition_resume_failed', [
                    'state_id' => $state->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $resumed;
    }

    private function isWaitingOnCondition(DripCampaignState $state): bool
    {
        $currentNodeId = (string) ($state->current_node_id ?? '');
        if ($currentNodeId === '') {
            return false;
        }

        $variables = (array) ($state->variables ?? []);

        return ! empty($variables['cond_waited_'.$currentNodeId]);
    }
}
