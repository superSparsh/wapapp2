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
                            {--discover : Only resolve/print the Meta USD CSV URL}';

    protected $description = 'Download Meta official USD WhatsApp rates and sync central country_pricing';

    public function handle(MetaWhatsAppUsdPricingSyncService $sync): int
    {
        if ($this->option('discover')) {
            return $this->discoverOnly($sync);
        }

        $this->info('Syncing Meta USD WhatsApp pricing…');

        try {
            $results = $sync->sync();
            $message = MetaPricingSyncMessageFormatter::oneLine($results, (int) ($results['plans_synced'] ?? 0));
            $this->info($message);
            Log::info('[operations:sync-meta-pricing] '.$message, [
                'batch_id' => $results['batch_id'] ?? null,
                'csv_url' => $results['csv_url'] ?? null,
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
            $this->line('Open https://developers.facebook.com/docs/whatsapp/pricing → "USD rates",');
            $this->line('copy the CSV link, and set META_USD_PRICING_CSV_URL in .env.');

            return self::FAILURE;
        }

        $this->info('Discovered URL:');
        $this->line('META_USD_PRICING_CSV_URL='.$url);

        return self::SUCCESS;
    }
}
