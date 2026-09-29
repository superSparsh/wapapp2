<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Jobs;

use App\Domains\Campaigns\Services\CampaignSendService;
use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Re-queue pending recipients after resume (keeps toggle HTTP request fast).
 */
class RedispatchPendingCampaignJobs implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $campaignId,
    ) {
        // Run on a light/web queue so resume works even when campaign/OCI workers are down.
        $this->onQueue((string) config('oci-workers.ephemeral.provisioning_queue', 'provisioning'));
    }

    public function handle(CampaignSendService $sendService): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        if ($campaign === null || ! $campaign->isSending()) {
            return;
        }

        $count = $sendService->dispatchPendingRecipientJobs($campaign);

        Log::info('Redispatched pending campaign recipients', [
            'campaign_id' => $this->campaignId,
            'dispatched' => $count,
        ]);
    }
}
