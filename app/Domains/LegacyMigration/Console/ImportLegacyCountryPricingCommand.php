<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\LegacyMigration\Services\LegacyCountryPricingImportService;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacyCountryPricingCommand extends Command
{
    protected $signature = 'legacy:import-country-pricing
                            {--dry-run : Preview without writing}
                            {--with-logs : Also copy country_pricing_logs}';

    protected $description = 'Import country_pricing rows from legacy WapApp into WapApp 2.0 central DB';

    public function handle(LegacyConnection $legacy, LegacyCountryPricingImportService $service): int
    {
        try {
            $legacy->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stats = $service->import(
            dryRun: $dryRun,
            withLogs: (bool) $this->option('with-logs'),
        );

        $this->info($dryRun ? 'Dry run complete.' : 'Legacy country pricing import complete.');
        $this->line('Created: '.$stats['created']);
        $this->line('Updated: '.$stats['updated']);
        $this->line('Skipped: '.$stats['skipped']);
        if ($this->option('with-logs')) {
            $this->line('Logs imported: '.$stats['logs_imported']);
        }

        return self::SUCCESS;
    }
}
