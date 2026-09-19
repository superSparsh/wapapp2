<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\LegacyMigration\DTO\MigrationOptions;
use App\Domains\LegacyMigration\Services\CustomerMigrationOrchestrator;
use App\Domains\LegacyMigration\Services\LegacyPlanImportService;
use App\Domains\LegacyMigration\Services\LegacySettingsImportService;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nightly legacy → 2.0 sync for every customer (all modules).
 *
 * Safe to re-run: importers upsert via MigrationIdMap + natural keys.
 * New legacy customers are discovered and migrated automatically.
 */
class SyncDailyLegacyCommand extends Command
{
    protected $signature = 'legacy:sync-daily
                            {--dry-run : Preview without writing}
                            {--limit= : Cap how many customers to process}
                            {--skip-inbox : Skip inbox threads/messages}
                            {--skip-billing : Skip wallet/billing}
                            {--skip-central : Skip plans/settings import}
                            {--force : Force even if LEGACY_DAILY_SYNC_ENABLED=false}';

    protected $description = 'Daily midnight sync: import/re-sync all legacy customer data into WapApp 2.0 (all modules, duplicate-safe)';

    public function handle(
        LegacyConnection $legacy,
        CustomerMigrationOrchestrator $orchestrator,
        LegacyPlanImportService $plans,
        LegacySettingsImportService $settings,
    ): int {
        if (! (bool) config('legacy-migration.daily_sync.enabled', true) && ! $this->option('force')) {
            $this->warn('Daily legacy sync is disabled (LEGACY_DAILY_SYNC_ENABLED=false). Pass --force to run anyway.');

            return self::SUCCESS;
        }

        try {
            $legacy->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            Log::error('legacy:sync-daily aborted — legacy DB not ready', [
                'error' => $exception->getMessage(),
            ]);

            return self::FAILURE;
        }

        $lockSeconds = (int) config('legacy-migration.daily_sync.lock_seconds', 82800);
        $lockKey = 'legacy-sync-daily-running';

        // Cache::add works on file/database/array drivers (Cache::lock needs redis/memcached).
        if (! Cache::add($lockKey, now()->toIso8601String(), max(60, $lockSeconds))) {
            $this->warn('Another legacy:sync-daily run is already in progress. Skipping.');

            return self::SUCCESS;
        }

        $startedAt = microtime(true);

        try {
            return $this->runSync($orchestrator, $plans, $settings, $startedAt);
        } finally {
            Cache::forget($lockKey);
        }
    }

    private function runSync(
        CustomerMigrationOrchestrator $orchestrator,
        LegacyPlanImportService $plans,
        LegacySettingsImportService $settings,
        float $startedAt,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $skipCentral = (bool) $this->option('skip-central');
        $skipInbox = (bool) $this->option('skip-inbox')
            || (bool) config('legacy-migration.daily_sync.skip_inbox', false);
        $skipBilling = (bool) $this->option('skip-billing')
            || (bool) config('legacy-migration.daily_sync.skip_billing', false);

        $limitOption = $this->option('limit');
        $limit = filled($limitOption)
            ? (int) $limitOption
            : (config('legacy-migration.daily_sync.limit') !== null
                ? (int) config('legacy-migration.daily_sync.limit')
                : null);
        if ($limit !== null && $limit <= 0) {
            $limit = null;
        }

        $this->info(sprintf(
            'legacy:sync-daily starting%s (inbox=%s billing=%s limit=%s)',
            $dryRun ? ' [DRY RUN]' : '',
            $skipInbox ? 'skip' : 'on',
            $skipBilling ? 'skip' : 'on',
            $limit === null ? 'all' : (string) $limit,
        ));

        if (! $skipCentral && ! $dryRun) {
            $this->syncCentral($plans, $settings);
        } elseif ($skipCentral) {
            $this->line('Skipping central plans/settings.');
        }

        $options = new MigrationOptions(
            dryRun: $dryRun,
            force: true,
            onlyModules: null,
            skipInbox: $skipInbox,
            skipBilling: $skipBilling,
        );

        $ok = 0;
        $failed = 0;
        $skipped = 0;
        $createdTenants = 0;

        $results = $orchestrator->syncAll($options, function (array $meta): void {
            $this->line(sprintf('→ customer #%s', $meta['id'] ?? '?'));
        }, $limit);

        foreach ($results as $result) {
            if (($result['skipped'] ?? false) === true) {
                $skipped++;
                $this->warn('  skipped (already running)');

                continue;
            }

            if (($result['error'] ?? null) !== null) {
                $failed++;
                $this->error('  FAILED: '.$result['error']);
                Log::warning('legacy:sync-daily customer failed', [
                    'legacy_customer_id' => $result['customer']?->id ?? null,
                    'error' => $result['error'],
                ]);

                continue;
            }

            $ok++;
            if (($result['created_tenant'] ?? false) === true) {
                $createdTenants++;
            }

            $customer = $result['customer'];
            $totals = $result['report']['totals'] ?? [];
            $this->line(sprintf(
                '  ok → tenant [%s]%s  +%d ~%d skip %d fail %d',
                $result['tenant_id'] ?? 'n/a',
                ($result['created_tenant'] ?? false) ? ' (new)' : '',
                $totals['created'] ?? 0,
                $totals['updated'] ?? 0,
                $totals['skipped'] ?? 0,
                $totals['failed'] ?? 0,
            ));

            if ($customer !== null) {
                Log::info('legacy:sync-daily customer synced', [
                    'legacy_customer_id' => $customer->id,
                    'tenant_id' => $result['tenant_id'],
                    'created_tenant' => $result['created_tenant'] ?? false,
                    'totals' => $totals,
                ]);
            }
        }

        $seconds = round(microtime(true) - $startedAt, 1);
        $summary = sprintf(
            'legacy:sync-daily done in %ss — ok=%d failed=%d skipped=%d new_tenants=%d',
            $seconds,
            $ok,
            $failed,
            $skipped,
            $createdTenants,
        );
        $this->newLine();
        $this->info($summary);
        Log::info($summary);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function syncCentral(LegacyPlanImportService $plans, LegacySettingsImportService $settings): void
    {
        if ((bool) config('legacy-migration.daily_sync.import_plans', true)) {
            $this->line('Central: importing plans…');
            try {
                $stats = $plans->import(dryRun: false, deactivateNonLegacy: false);
                $this->line(sprintf(
                    '  plans created=%d updated=%d skipped=%d',
                    $stats['created'] ?? 0,
                    $stats['updated'] ?? 0,
                    $stats['skipped'] ?? 0,
                ));

                if ((bool) config('legacy-migration.daily_sync.assign_tenant_plans', true)) {
                    $assign = $plans->assignTenantPlans(dryRun: false);
                    $this->line(sprintf(
                        '  tenant plans assigned=%d skipped=%d',
                        $assign['assigned'] ?? 0,
                        $assign['skipped'] ?? 0,
                    ));
                }
            } catch (Throwable $exception) {
                $this->error('  plans import failed: '.$exception->getMessage());
                Log::warning('legacy:sync-daily plans import failed', ['error' => $exception->getMessage()]);
            }
        }

        if ((bool) config('legacy-migration.daily_sync.import_settings', true)) {
            $this->line('Central: importing settings…');
            try {
                $stats = $settings->import(dryRun: false, includeExtras: false);
                $this->line(sprintf(
                    '  settings mapped=%d derived=%d skipped=%d',
                    $stats['mapped'] ?? 0,
                    $stats['derived'] ?? 0,
                    $stats['skipped'] ?? 0,
                ));
            } catch (Throwable $exception) {
                $this->error('  settings import failed: '.$exception->getMessage());
                Log::warning('legacy:sync-daily settings import failed', ['error' => $exception->getMessage()]);
            }
        }
    }
}
