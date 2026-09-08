<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\LegacyMigration\DTO\MigrationOptions;
use App\Domains\LegacyMigration\Services\CustomerMigrationOrchestrator;
use App\Domains\LegacyMigration\Services\LegacyCustomerResolver;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use Illuminate\Console\Command;
use Throwable;

class MigrateLegacyCustomerCommand extends Command
{
    protected $signature = 'legacy:migrate-customer
                            {customer? : Legacy customer id, uid, or owner email}
                            {--all : Migrate every legacy customer with data}
                            {--pilot : Auto-pick a mid-size customer for first pull}
                            {--dry-run : Preview counts without writing}
                            {--force : Re-sync even if already migrated}
                            {--skip-inbox : Skip inbox threads/messages}
                            {--skip-billing : Skip wallet/billing}
                            {--only=* : Limit to modules (owner,lines,lists,contacts,templates,interactive_messages,variables,forms,trigger_templates,team,campaigns,chatbots,drips,whatsapp_flows,ai,inbox,billing,integrations)}';

    protected $description = 'Migrate one or all legacy WapApp customers into WapApp 2.0 tenants (duplicate-safe). FAQs/tutorials: php artisan help-center:import-legacy --force';

    public function handle(
        LegacyConnection $legacy,
        LegacyCustomerResolver $resolver,
        CustomerMigrationOrchestrator $orchestrator,
    ): int {
        try {
            $legacy->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->line('Set LEGACY_DB_* in .env to your production/legacy MySQL credentials.');

            return self::FAILURE;
        }

        $options = new MigrationOptions(
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force'),
            onlyModules: $this->option('only') ?: null,
            skipInbox: (bool) $this->option('skip-inbox'),
            skipBilling: (bool) $this->option('skip-billing'),
        );

        if ($this->option('all')) {
            return $this->migrateAll($orchestrator, $options);
        }

        if ($this->option('pilot')) {
            $this->info('Selecting pilot customer…');
            try {
                $result = $orchestrator->migratePilot($options);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->printResult($result);

            return self::SUCCESS;
        }

        $customer = $this->argument('customer');
        if (! filled($customer)) {
            $this->error('Pass a customer id/email/uid, or use --pilot / --all.');
            $this->line('Tip: php artisan legacy:list-customers');

            return self::FAILURE;
        }

        try {
            $result = $orchestrator->migrate((string) $customer, $options);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->printResult($result);

        return self::SUCCESS;
    }

    private function migrateAll(CustomerMigrationOrchestrator $orchestrator, MigrationOptions $options): int
    {
        if (! $options->dryRun && ! $this->confirm('This will migrate ALL legacy customers. Continue?')) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $failures = 0;
        $results = $orchestrator->migrateAll($options, function (array $candidate): void {
            $this->line(sprintf(
                '→ #%s %s (%s)',
                $candidate['id'],
                $candidate['email'] ?? 'no-email',
                $candidate['company'] ?? 'n/a',
            ));
        });

        foreach ($results as $result) {
            if (($result['error'] ?? null) !== null) {
                $failures++;
                $this->error('  FAILED: '.$result['error']);

                continue;
            }

            $this->printResult($result, compact: true);
        }

        $this->newLine();
        $this->info('Done. Failures: '.$failures.'/'.count($results));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function printResult(array $result, bool $compact = false): void
    {
        /** @var \App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot $customer */
        $customer = $result['customer'];
        $prefix = $compact ? '  ' : '';

        $this->line($prefix.sprintf(
            'Customer #%d <%s> → tenant [%s]%s%s',
            $customer->id,
            $customer->email ?? 'n/a',
            $result['tenant_id'] ?? 'n/a',
            $result['created_tenant'] ? ' (new)' : ' (reused)',
            $result['dry_run'] ? ' [DRY RUN]' : '',
        ));

        if (! empty($result['reused_reason'])) {
            $this->line($prefix.'Reuse reason: '.$result['reused_reason']);
        }

        if ($result['dry_run'] && isset($result['report']['preview']['counts'])) {
            $this->table(
                ['Group', 'Rows'],
                collect($result['report']['preview']['counts'])
                    ->map(fn ($count, $key) => [$key, $count])
                    ->values()
                    ->all(),
            );

            return;
        }

        $modules = $result['report']['modules'] ?? [];
        if ($modules !== []) {
            $this->table(
                ['Module', 'Created', 'Updated', 'Skipped', 'Failed'],
                collect($modules)->map(fn ($stats, $module) => [
                    $module,
                    $stats['created'] ?? 0,
                    $stats['updated'] ?? 0,
                    $stats['skipped'] ?? 0,
                    $stats['failed'] ?? 0,
                ])->values()->all(),
            );
        }

        foreach ($result['report']['warnings'] ?? [] as $warning) {
            $this->warn($prefix.$warning);
        }
    }
}
