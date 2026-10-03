<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Domains\Operations\Services\MetaPricing\MetaPricingSyncMessageFormatter;
use App\Domains\Operations\Services\MetaPricing\MetaWhatsAppUsdPricingSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMetaPricingCommand extends Command
{
    protected $signature = 'operations:sync-meta-pricing
                            {--discover : Only resolve/print the Meta USD CSV URL}
                            {--dry-run : Download + preview changes without writing to the database}';

    protected $description = 'Download Meta official USD WhatsApp rates and sync central country_pricing';

    public function handle(MetaWhatsAppUsdPricingSyncService $sync): int
    {
        if ($this->option('discover')) {
            return $this->discoverOnly($sync);
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun
            ? 'Dry-run: downloading Meta USD WhatsApp pricing (no DB writes)…'
            : 'Syncing Meta USD WhatsApp pricing…');

        try {
            $results = $sync->sync(dryRun: $dryRun);
            $message = MetaPricingSyncMessageFormatter::oneLine($results, (int) ($results['plans_synced'] ?? 0));
            if ($dryRun) {
                $this->warn('[DRY RUN] '.$message);
                $this->renderDryRunPreview($results);
            } else {
                $this->info($message);
            }

            Log::info('[operations:sync-meta-pricing] '.($dryRun ? '[dry-run] ' : '').$message, [
                'batch_id' => $results['batch_id'] ?? null,
                'csv_url' => $results['csv_url'] ?? null,
                'dry_run' => $dryRun,
                'updated' => count($results['updated'] ?? []),
                'skipped' => count($results['skipped'] ?? []),
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            Log::error('[operations:sync-meta-pricing] failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $results
     */
    private function renderDryRunPreview(array $results): void
    {
        $updated = $results['updated'] ?? [];
        if (! is_array($updated) || $updated === []) {
            $this->line('No price changes would be applied.');

            return;
        }

        $rows = [];
        foreach (array_slice($updated, 0, 40) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $changes = is_array($item['changes'] ?? null) ? $item['changes'] : [];
            $parts = [];
            foreach ($changes as $field => $change) {
                if (! is_array($change)) {
                    continue;
                }
                $parts[] = $field.': '.($change['old'] ?? 'null').' → '.($change['new'] ?? 'null');
            }
            $rows[] = [
                (string) ($item['country'] ?? $item['market'] ?? '-'),
                implode('; ', $parts) ?: '-',
            ];
        }

        $this->table(['Country / market', 'Changes'], $rows);

        $total = count($updated);
        if ($total > 40) {
            $this->line('…and '.($total - 40).' more.');
        }
    }

    private function discoverOnly(MetaWhatsAppUsdPricingSyncService $sync): int
    {
        $configured = config('services.whatsapp_meta_pricing.usd_csv_url');
        if (! empty($configured)) {
            $this->info('Configured in .env (META_USD_PRICING_CSV_URL):');
            $this->line((string) $configured);

            return self::SUCCESS;
        }

        $this->info('Discovering from Meta developer docs…');
        $url = $sync->discoverUsdRatesCsvUrl();

        if (empty($url)) {
            $this->error('Could not discover CSV URL.');
            $this->line('Open https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/ → "USD list rates",');
            $this->line('copy the CSV/XLSX link, and set META_USD_PRICING_CSV_URL in .env.');

            return self::FAILURE;
        }

        $this->info('Discovered URL:');
        $this->line('META_USD_PRICING_CSV_URL='.$url);

        return self::SUCCESS;
    }
}
