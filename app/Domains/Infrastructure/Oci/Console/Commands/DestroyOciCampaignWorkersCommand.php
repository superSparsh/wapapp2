<?php

declare(strict_types=1);

namespace App\Domains\Infrastructure\Oci\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Domains\Campaigns\Services\CampaignSendService;
use App\Domains\Infrastructure\Oci\CampaignOciWorkerLifecycle;
use App\Domains\Infrastructure\Oci\Contracts\OciContainerInstanceClient;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Nightly (or on-demand) force-destroy of ephemeral campaign Container Instances.
 * Optionally pauses any still-Sending campaigns so UI / OCI refcount stay consistent.
 */
class DestroyOciCampaignWorkersCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'oci:destroy-campaign-workers
        {--dry-run : Log what would be destroyed without deleting}
        {--no-pause-sending : Do not pause Sending campaigns after destroy}
        {--tenants=* : Limit pause sweep to these tenant IDs}';

    protected $description = 'Force-destroy active OCI campaign worker containers and clear Redis lifecycle state.';

    public function handle(
        CampaignOciWorkerLifecycle $lifecycle,
        OciContainerInstanceClient $client,
        CampaignSendService $sendService,
    ): int {
        if ($this->skipForMaintenance('campaigns', 'OCI destroy:')) {
            return self::SUCCESS;
        }

        if (! $lifecycle->enabled() && ! $this->option('dry-run')) {
            $this->warn('OCI ephemeral workers are disabled (OCI_WORKERS_ENABLED / OCI_EPHEMERAL_CONTAINERS).');
        }

        $tracked = $lifecycle->instanceOcid();
        $startedAt = $lifecycle->instanceStartedAt();
        $this->info('Tracked OCID: '.($tracked ?: '(none)'));
        if ($startedAt !== null) {
            $this->line('Container started_at: '.$startedAt);
        }

        if ($this->option('dry-run')) {
            $orphans = [];
            try {
                if ($client->isConfigured()) {
                    $orphans = $client->listCampaignWorkerOcids();
                }
            } catch (\Throwable $e) {
                $this->warn('Could not list OCI instances: '.$e->getMessage());
            }

            $this->warn('Dry-run: would delete tracked + '.count($orphans).' listed instance(s).');
            if ($tracked) {
                $this->line('  - '.$tracked.' (tracked)');
            }
            foreach ($orphans as $ocid) {
                $this->line('  - '.$ocid);
            }

            return self::SUCCESS;
        }

        try {
            $result = $lifecycle->forceDestroy($client, includeOrphans: true);
        } catch (\Throwable $e) {
            Log::error('oci:destroy-campaign-workers failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Deleted '.count($result['deleted']).' container instance(s).');
        foreach ($result['deleted'] as $ocid) {
            $this->line('  - '.$ocid);
        }

        if (is_array($result['session'] ?? null)) {
            $seconds = (int) ($result['session']['active_seconds'] ?? 0);
            $this->info(sprintf(
                'Last container session: %s → %s (%s)',
                $result['session']['started_at'] ?? '-',
                $result['session']['ended_at'] ?? '-',
                $this->formatSeconds($seconds),
            ));
        }

        if (! $this->option('no-pause-sending')) {
            $paused = 0;
            $this->foreachTenant(function () use ($sendService, &$paused): void {
                $paused += $sendService->pauseAllSendingCampaigns(reason: 'nightly_oci_destroy');
            });
            $this->info("Paused {$paused} sending campaign(s) across tenants.");
        }

        return self::SUCCESS;
    }

    private function formatSeconds(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%dh %dm %ds', $h, $m, $s);
        }
        if ($m > 0) {
            return sprintf('%dm %ds', $m, $s);
        }

        return sprintf('%ds', $s);
    }
}
