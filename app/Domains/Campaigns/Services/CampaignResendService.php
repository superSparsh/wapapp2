<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Campaigns\Jobs\SendCampaignRecipientJob;
use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Support\OciWorkload;
use Illuminate\Support\Facades\Log;
use Throwable;

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
                        ->onQueue(OciWorkload::campaignQueue());

                    $count++;
                }
            });

        if ($count > 0) {
            $campaign->update([
                'status' => CampaignStatus::Sending,
                'completed_at' => null,
            ]);

            try {
                app(CampaignOciWorkerLifecycle::class)->onCampaignStarted($campaign->fresh() ?? $campaign);
            } catch (Throwable $e) {
                Log::warning('OCI campaign worker provision on resend failed', ['error' => $e->getMessage()]);
            }
        }

        return $count;
    }
}
