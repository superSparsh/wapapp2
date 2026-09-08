<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Account\Services\DataDeletionService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ProcessDataDeletionSchedules extends Command
{
    protected $signature = 'data-deletion:process {--tenants=* : Tenant IDs to process}';

    protected $description = 'Run due data deletion schedules and expire old exports';

    public function handle(DataDeletionService $dataDeletionService): int
    {
        $tenantIds = $this->option('tenants');
        $tenants = $tenantIds
            ? Tenant::query()->whereIn('id', $tenantIds)->get()
            : Tenant::query()->get();

        $totalSchedules = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            $processed = $dataDeletionService->processDueSchedules();
            $dataDeletionService->markExpiredExports();

            if ($processed > 0) {
                $this->line("Tenant {$tenant->id}: processed {$processed} deletion schedule(s).");
            }

            $totalSchedules += $processed;
        }

        $this->info("Finished. Processed {$totalSchedules} deletion schedule(s).");

        return self::SUCCESS;
    }
}
