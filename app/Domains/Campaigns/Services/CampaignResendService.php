<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;

class CampaignResendService
{
    public function resendFailed(Campaign $campaign): int
    {
        $count = 0;

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Failed)
            ->orderBy('id')
            ->chunkById(100, function ($recipients) use ($campaign, &$count): void {
                foreach ($recipients as $recipient) {
                    $recipient->update([
                        'status' => CampaignRecipientStatus::Pending,
                        'failed_at' => null,
                        'failure_reason' => null,
                    ]);

                    SendCampaignRecipientJob::dispatch((int) $campaign->id, (int) $recipient->id)
                        ->onQueue((string) config('campaigns.queue', 'default'));

                    $count++;
                }
            });

        return $count;
    }
}
