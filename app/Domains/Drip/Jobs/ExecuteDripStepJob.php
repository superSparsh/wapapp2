<?php

declare(strict_types=1);

namespace App\Domains\Drip\Jobs;

use App\Domains\Drip\Services\DripFlowEngine;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\DripCampaignState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExecuteDripStepJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $dripCampaignStateId,
    ) {}

    public function handle(DripFlowEngine $engine): void
    {
        $state = DripCampaignState::query()->find($this->dripCampaignStateId);

        if ($state === null || $state->status->isTerminal()) {
            return;
        }

        if ($state->status === ChatbotFlowStateStatus::Waiting) {
            $state->forceFill(['status' => ChatbotFlowStateStatus::Active])->save();
        }

        $engine->executeFromState($state);
    }
}
