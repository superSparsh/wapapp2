<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\LegacyMigration\Services\LegacyPlanImportService;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyPlansCommand extends Command
{
    protected $signature = 'legacy:import-plans
                            {--dry-run : Preview without writing}
                            {--ensure-default : Only create a default active plan if none exists}';

    protected $description = 'Import plans from legacy WapApp into WapApp 2.0 central plans table';

    public function handle(LegacyConnection $legacy, LegacyPlanImportService $service): int
    {
        if ($this->option('ensure-default')) {
            $plan = $service->ensureDefaultPlan();
            $this->info("Active plan ready: #{$plan->id} {$plan->name} ({$plan->slug})");

            return self::SUCCESS;
        }

        try {
            $legacy->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stats = $service->import(dryRun: $dryRun);

        $this->info($dryRun ? 'Dry run complete.' : 'Legacy plans import complete.');
        $this->line('Created: '.$stats['created']);
        $this->line('Updated: '.$stats['updated']);
        $this->line('Skipped: '.$stats['skipped']);

        return self::SUCCESS;
    }
}
