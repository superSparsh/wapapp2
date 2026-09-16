<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Interfaces\CampaignRepositoryInterface;
use App\Services\CampaignSendService;
use Illuminate\Console\Command;

class ProcessDueCampaignsCommand extends Command
{
    protected $signature = 'campaigns:process-due';

    protected $description = 'Queue due scheduled campaigns for sending.';

    public function handle(
        CampaignRepositoryInterface $campaignRepo,
        CampaignSendService $sendService,
    ): int {
        $due = $campaignRepo->getDueScheduledCampaigns();
        $processed = 0;

        foreach ($due as $campaign) {
            $sendService->queueCampaign($campaign);
            $processed++;
        }

        $this->info("Queued {$processed} due campaign(s).");

        return self::SUCCESS;
    }
}
