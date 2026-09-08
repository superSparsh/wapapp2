<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Console;

use App\Domains\LegacyMigration\Services\LegacyCustomerResolver;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use Illuminate\Console\Command;
use Throwable;

class ListLegacyCustomersCommand extends Command
{
    protected $signature = 'legacy:list-customers
                            {--limit=20 : How many customers to show}';

    protected $description = 'List legacy customers ranked by data volume (for choosing who to migrate)';

    public function handle(LegacyConnection $legacy, LegacyCustomerResolver $resolver): int
    {
        try {
            $legacy->assertReady();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $rows = $resolver->listCandidates(limit: (int) $this->option('limit'));

        if ($rows->isEmpty()) {
            $this->warn('No customers found on the legacy connection.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Email', 'Company', 'Lines', 'Lists', 'Subs', 'Templates', 'Campaigns', 'Bots', 'Drips', 'Flows', 'Team', 'Inbox'],
            $rows->map(fn (array $row) => [
                $row['id'],
                $row['email'] ?? '',
                \Illuminate\Support\Str::limit((string) ($row['company'] ?? ''), 24),
                $row['lines'] ?? 0,
                $row['lists'] ?? 0,
                $row['subscribers'] ?? 0,
                $row['templates'] ?? 0,
                $row['campaigns'] ?? 0,
                $row['chatbots'] ?? 0,
                $row['drips'] ?? 0,
                $row['flows'] ?? 0,
                $row['team'] ?? 0,
                $row['inbox_threads'] ?? 0,
            ])->all(),
        );

        $this->newLine();
        $this->info('Pilot pick suggestion:');
        try {
            $pilot = $resolver->resolvePilot();
            $this->line(sprintf(
                '  #%d %s (%s) — subs=%d templates=%d campaigns=%d',
                $pilot->id,
                $pilot->email,
                $pilot->displayName(),
                $pilot->counts['subscribers'] ?? 0,
                $pilot->counts['templates'] ?? 0,
                $pilot->counts['campaigns'] ?? 0,
            ));
            $this->line('  Run: php artisan legacy:migrate-customer --pilot --dry-run');
        } catch (Throwable $exception) {
            $this->warn('  '.$exception->getMessage());
        }

        return self::SUCCESS;
    }
}
