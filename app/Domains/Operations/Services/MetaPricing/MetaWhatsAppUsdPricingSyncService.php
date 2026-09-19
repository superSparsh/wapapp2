<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services\MetaPricing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MetaWhatsAppUsdPricingSyncService
{
    private const DOCS_URL = 'https://developers.facebook.com/docs/whatsapp/pricing';

    /**
     * Download Meta's official USD rate card CSV and import into country_pricing.
     *
     * @return array{updated: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: list<array<string, mixed>>, csv_url: string, batch_id: string, plans_synced: int}
     */
    public function sync(?int $updatedBy = null, string $source = 'meta_sync'): array
    {
        $batchId = (string) Str::uuid();

        $csvUrl = config('services.whatsapp_meta_pricing.usd_csv_url');
        if (empty($csvUrl)) {
            $csvUrl = $this->discoverUsdRatesCsvUrl();
        }

        if (empty($csvUrl)) {
            throw new RuntimeException(
                'Could not resolve Meta USD rates CSV URL. '
                .'Run: php artisan operations:discover-meta-pricing-csv-url — or set META_USD_PRICING_CSV_URL in .env '
               .'(download link from developers.facebook.com/docs/whatsapp/pricing → "USD rates"). '
               .'Ensure the server can reach Facebook (HTTPS outbound).'
            );
        }

        $response = Http::timeout(120)
            ->withHeaders(['User-Agent' => 'WAPAPP-MetaPricingSync/1.0'])
            ->get($csvUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download Meta USD CSV: HTTP '.$response->status());
        }

        $tempBase = tempnam(sys_get_temp_dir(), 'meta_usd_');
        if ($tempBase === false) {
            throw new RuntimeException('Unable to create temporary file for Meta CSV.');
        }
        $tempPath = $tempBase.'.csv';
        @unlink($tempBase);
        file_put_contents($tempPath, $response->body());

        try {
            $import = new ImportMetaPricingService;
            $import->setPricingCurrency('USD');
            $import->setAuditContext($batchId, $source, $updatedBy);
            $results = $import->importFromCsv($tempPath);

            return array_merge($results, [
                'csv_url' => $csvUrl,
                'plans_synced' => 0,
                'batch_id' => $batchId,
            ]);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function discoverUsdRatesCsvUrl(): ?string
    {
        $sources = [
            self::DOCS_URL,
            'https://developers.facebook.com/docs/whatsapp/pricing/conversation-based-pricing',
        ];

        foreach ($sources as $pageUrl) {
            try {
                $html = Http::timeout(45)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; WAPAPP-MetaPricingSync/1.0)',
                        'Accept' => 'text/html,application/xhtml+xml',
                        'Accept-Language' => 'en-US,en;q=0.9',
                    ])
                    ->get($pageUrl)
                    ->body();

                if ($url = $this->extractUsdCsvUrlFromHtml($html)) {
                    return $url;
                }
            } catch (\Throwable $e) {
                Log::warning('Meta USD CSV discovery failed for '.$pageUrl.': '.$e->getMessage());
            }
        }

        return null;
    }

    public function extractUsdCsvUrlFromHtml(string $html): ?string
    {
        if (preg_match('#href="(https://l\.facebook\.com/l\.php\?u=[^"]+)"[^>]*>\s*USD rates\s*</a>#i', $html, $m)) {
            $url = $this->unwrapFacebookRedirect($m[1]);
            if ($url !== '') {
                return $url;
            }
        }

        if (preg_match('#l\.php\?u=([^"&]+)[^"]*"[^>]*>\s*USD rates\s*</a>#i', $html, $m)) {
            $url = urldecode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            if (str_contains($url, '.csv')) {
                return $url;
            }
        }

        if (preg_match('#https://scontent[^"\'\s<>]+\.csv[^"\'\s<>]*#i', $html, $m)) {
            return html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }

    private function unwrapFacebookRedirect(string $lPhpUrl): string
    {
        $query = parse_url(html_entity_decode($lPhpUrl), PHP_URL_QUERY);
        parse_str((string) $query, $params);

        return urldecode((string) ($params['u'] ?? ''));
    }
}
