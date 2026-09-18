<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\Campaigns\Contracts\CampaignServiceClientInterface;
use App\Domains\Campaigns\Services\CampaignSendService;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessDueCampaignsCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'campaigns:process-due {--tenants=* : Tenant IDs to process}';

    protected $description = 'Queue due scheduled campaigns and resume sending campaigns.';

    public function handle(
        CampaignSendService $sendService,
        CampaignServiceClientInterface $campaignClient,
    ): int {
        if ($this->skipForMaintenance('campaigns', 'Campaigns:')) {
            return self::SUCCESS;
        }

        $useMicroservice = (bool) config('campaign-service.enabled', false);
        $total = 0;

        $this->foreachTenant(function () use ($sendService, $campaignClient, $useMicroservice, &$total): void {
            if ($useMicroservice) {
                try {
                    $result = $campaignClient->processDue();
                    $total += (int) ($result['processed'] ?? 0);

                    return;
                } catch (\Throwable $e) {
                    Log::warning('Campaign microservice processDue failed; falling back to local', [
                        'error' => $e->getMessage(),
                    ]);

                    if (! (bool) config('campaign-service.fallback_to_local', true)) {
                        throw $e;
                    }
                }
            }

            $due = Campaign::query()
                ->where('status', CampaignStatus::Scheduled)
                ->where('scheduled_at', '<=', now())
                ->get();

            foreach ($due as $campaign) {
                $sendService->queueCampaign($campaign);
                $total++;
            }
        });

        $this->info("Queued {$total} due campaign(s).");

        return self::SUCCESS;
    }
}
