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

    protected $description = 'Queue due scheduled campaigns, reconcile finished Sending, and auto-pause stuck Sending.';

    public function handle(CampaignSendService $sendService): int
    {
        if ($this->skipForMaintenance('campaigns', 'Campaigns:')) {
            return self::SUCCESS;
        }

        $total = 0;
        $reconciled = 0;
        $pausedIdle = 0;

        $this->foreachTenant(function () use ($sendService, &$total, &$reconciled, &$pausedIdle): void {
            $due = Campaign::query()
                ->where('status', CampaignStatus::Scheduled)
                ->where('scheduled_at', '<=', now())
                ->get();

            foreach ($due as $campaign) {
                $sendService->queueCampaign($campaign);
                $total++;
            }

            $reconciled += $sendService->reconcileStuckSendingCampaigns();
            $pausedIdle += $sendService->pauseIdleSendingCampaigns();
        });

        $pruned = 0;
        try {
            $pruned = app(\App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle::class)
                ->pruneStaleActiveCampaignRefs();
        } catch (\Throwable $e) {
            $this->warn('OCI active-ref prune failed: '.$e->getMessage());
        }

        $this->info("Queued {$total} due campaign(s). Reconciled {$reconciled}. Auto-paused idle {$pausedIdle}. OCI refs pruned {$pruned}.");

        return self::SUCCESS;
    }
}
