<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Domains\Operations\Services\MetaPricing\MetaWhatsAppUsdPricingSyncService;
use Illuminate\Console\Command;

class DiscoverMetaPricingCsvUrlCommand extends Command
{
    protected $signature = 'operations:discover-meta-pricing-csv-url';

    protected $description = 'Resolve Meta official USD pricing CSV URL (for META_USD_PRICING_CSV_URL)';

    public function handle(MetaWhatsAppUsdPricingSyncService $sync): int
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
            $this->line('Your server may block outbound HTTPS to developers.facebook.com / fbcdn.net.');
            $this->line('Fix: open https://developers.facebook.com/docs/whatsapp/pricing in a browser,');
            $this->line('click "USD rates" download, copy the CSV link, and add to .env:');
            $this->line('META_USD_PRICING_CSV_URL=<paste-url-here>');
            $this->line('Then: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('Discovered URL (add to .env if auto-fetch keeps failing):');
        $this->line('');
        $this->line('META_USD_PRICING_CSV_URL='.$url);

        return self::SUCCESS;
    }
}
