<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Jobs;

use App\Domains\Campaigns\Services\CampaignSendService;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignRecipientJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $campaignId,
        public readonly int $recipientId,
    ) {}

    public function handle(CampaignSendService $sendService): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        $recipient = CampaignRecipient::query()->find($this->recipientId);

        if ($campaign === null || $recipient === null) {
            return;
        }

        $sendService->sendRecipient($campaign, $recipient);
    }
}
