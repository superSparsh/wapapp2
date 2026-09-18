<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\LegacyMigration\Services\LegacySettingsImportService;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use Illuminate\Console\Command;
use Throwable;

class ImportLegacySettingsCommand extends Command
{
    protected $signature = 'legacy:import-settings
                            {--dry-run : Preview without writing}
                            {--extras : Also store unmapped legacy settings as legacy.* keys}';

    protected $description = 'Import legacy admin settings into WapApp 2.0 platform_settings';

    public function handle(LegacyConnection $legacy, LegacySettingsImportService $service): int
    {
        try {
            $legacy->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $extras = (bool) $this->option('extras');

        $stats = $service->import(dryRun: $dryRun, includeExtras: $extras);

        $this->info($dryRun ? 'Dry run complete.' : 'Legacy settings import complete.');
        $this->line('Mapped keys: '.$stats['mapped']);
        $this->line('Derived keys: '.$stats['derived']);
        $this->line('Extra legacy.* keys: '.$stats['extras']);
        $this->line('Skipped (missing in legacy): '.$stats['skipped']);

        if (! $dryRun) {
            $this->comment('Open Admin → Settings to verify app name, mailer, wallet, Razorpay, OAuth.');
        }

        return self::SUCCESS;
    }
}
