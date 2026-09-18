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
                            {--ensure-default : Only create a default active plan if none exists}
                            {--deactivate-non-legacy : Deactivate seeder/dummy plans that are not from legacy}
                            {--assign-tenants : Set each migrated tenant plan_id from their legacy subscription}';

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
        $stats = $service->import(
            dryRun: $dryRun,
            deactivateNonLegacy: (bool) $this->option('deactivate-non-legacy'),
        );

        $this->info($dryRun ? 'Dry run complete.' : 'Legacy plans import complete.');
        $this->line('Created: '.$stats['created']);
        $this->line('Updated: '.$stats['updated']);
        $this->line('Skipped: '.$stats['skipped']);
        $this->line('Deactivated non-legacy: '.$stats['deactivated']);

        if ($this->option('assign-tenants')) {
            $assign = $service->assignTenantPlans(dryRun: $dryRun);
            $this->newLine();
            $this->info($dryRun ? 'Tenant plan assignment (dry run):' : 'Tenant plan assignment:');
            $this->line('Assigned: '.$assign['assigned']);
            $this->line('Already correct: '.$assign['skipped']);
            $this->line('No legacy subscription: '.$assign['missing_subscription']);
            $this->line('Legacy plan not imported: '.$assign['missing_plan']);
        }

        return self::SUCCESS;
    }
}
