<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\Campaigns\Services\CampaignSendService;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class ProcessDueCampaignsCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'campaigns:process-due {--tenants=* : Tenant IDs to process}';

    protected $description = 'Queue due scheduled campaigns and resume sending campaigns.';

    public function handle(CampaignSendService $sendService): int
    {
        if ($this->skipForMaintenance('campaigns', 'Campaigns:')) {
            return self::SUCCESS;
        }

        $total = 0;

        $this->foreachTenant(function () use ($sendService, &$total): void {
            $due = Campaign::query()
                ->where('status', CampaignStatus::Scheduled)
                ->where('scheduled_at', '<=', now())
                ->get();

            foreach ($due as $campaign) {
                $sendService->queueCampaign($campaign);
                $total++;
            }

            $sendService->reconcileStuckSendingCampaigns();
        });

        $this->info("Queued {$total} due campaign(s).");

        return self::SUCCESS;
    }
}
