<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services\MetaPricing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class MetaWhatsAppUsdPricingSyncService
{
    private const DOCS_URLS = [
        'https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing/',
        'https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing',
        'https://developers.facebook.com/docs/whatsapp/pricing',
        'https://developers.facebook.com/docs/whatsapp/pricing/conversation-based-pricing',
    ];

    private const CACHED_URL_KEY = 'operations.meta_pricing.usd_list_rates_url';

    /**
     * Download Meta's official USD rate card and import into country_pricing.
     *
     * @return array{updated: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: list<array<string, mixed>>, csv_url: string, batch_id: string, plans_synced: int}
     */
    public function sync(?int $updatedBy = null, string $source = 'meta_sync', bool $dryRun = false): array
    {
        $batchId = (string) Str::uuid();

        $csvUrl = config('services.whatsapp_meta_pricing.usd_csv_url');
        if (empty($csvUrl)) {
            $csvUrl = $this->discoverUsdRatesCsvUrl();
        }
        if (empty($csvUrl)) {
            $cached = Cache::get(self::CACHED_URL_KEY);
            $csvUrl = is_string($cached) && $cached !== '' ? $cached : null;
        }

        if (empty($csvUrl)) {
            throw new RuntimeException(
                'Could not resolve Meta USD rates CSV URL. '
                .'Run: php artisan operations:discover-meta-pricing-csv-url - or set META_USD_PRICING_CSV_URL in .env '
                .'(developers.facebook.com → WhatsApp pricing → "USD list rates"). '
                .'Ensure the server can reach Facebook (HTTPS outbound).'
            );
        }

        $body = $this->downloadBinary((string) $csvUrl);
        $tempPath = $this->materializeImportCsv($body);

        try {
            $import = new ImportMetaPricingService;
            $import->setPricingCurrency('USD');
            $import->setAuditContext($batchId, $source, $updatedBy);
            $results = $import->importFromCsv($tempPath, $dryRun);

            if (! $dryRun) {
                Cache::put(self::CACHED_URL_KEY, (string) $csvUrl, now()->addDays(30));
            }

            return array_merge($results, [
                'csv_url' => $csvUrl,
                'plans_synced' => 0,
                'batch_id' => $batchId,
                'dry_run' => $dryRun,
            ]);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function discoverUsdRatesCsvUrl(): ?string
    {
        foreach (self::DOCS_URLS as $pageUrl) {
            try {
                $html = $this->fetchHtml($pageUrl);
                if ($html === null || $html === '') {
                    continue;
                }

                if ($url = $this->extractUsdCsvUrlFromHtml($html)) {
                    return $url;
                }
            } catch (Throwable $e) {
                Log::warning('Meta USD CSV discovery failed for '.$pageUrl.': '.$e->getMessage());
            }
        }

        return null;
    }

    public function extractUsdCsvUrlFromHtml(string $html): ?string
    {
        // Current Meta label (per-message pricing rate cards).
        $preferredLabels = [
            'USD list rates',
            'USD rates',
        ];

        foreach ($preferredLabels as $label) {
            $pattern = '#href="(https://l\.facebook\.com/l\.php\?u=[^"]+)"[^>]*>\s*'
                .preg_quote($label, '#')
                .'\s*</a>#i';
            if (preg_match($pattern, $html, $m)) {
                $url = $this->unwrapFacebookRedirect($m[1]);
                if ($this->looksLikeSpreadsheetUrl($url)) {
                    return $url;
                }
            }
        }

        // JSON-embedded docs payload sometimes contains escaped scontent CSV URLs near the label.
        if (preg_match(
            '#USD list rates.{0,400}?https:\\\\/\\\\/(scontent[^"\\\\]+?\\.csv[^"\\\\]*)#is',
            $html,
            $m
        )) {
            $url = stripcslashes('https://'.$m[1]);

            return html_entity_decode($url, ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('#l\.php\?u=([^"&]+)[^"]*"[^>]*>\s*USD list rates\s*</a>#i', $html, $m)) {
            $url = urldecode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
            if ($this->looksLikeSpreadsheetUrl($url)) {
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

    private function looksLikeSpreadsheetUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return str_ends_with($path, '.csv') || str_ends_with($path, '.xlsx');
    }

    private function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    private function fetchHtml(string $pageUrl): ?string
    {
        $headers = $this->browserHeaders();

        try {
            $response = Http::timeout(45)->withHeaders($headers)->get($pageUrl);
            if ($response->successful() && strlen($response->body()) > 2000) {
                return $response->body();
            }
        } catch (Throwable $e) {
            Log::warning('Meta docs Http fetch failed for '.$pageUrl.': '.$e->getMessage());
        }

        // Facebook often returns HTTP 400 without Sec-Fetch headers / HTTP client quirks.
        return $this->curlGet($pageUrl, $headers);
    }

    private function downloadBinary(string $url): string
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept' => '*/*',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'cross-site',
        ];

        try {
            $response = Http::timeout(120)->withHeaders($headers)->get($url);
            if ($response->successful() && $response->body() !== '') {
                return $response->body();
            }

            throw new RuntimeException('HTTP '.$response->status());
        } catch (Throwable $e) {
            $viaCurl = $this->curlGet($url, $headers);
            if ($viaCurl !== null && $viaCurl !== '') {
                return $viaCurl;
            }

            throw new RuntimeException('Failed to download Meta USD rate card: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function curlGet(string $url, array $headers): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (! is_string($body) || $body === '' || $status < 200 || $status >= 300) {
            return null;
        }

        return $body;
    }

    /**
     * Meta currently ships "USD list rates" as an XLSX file with a .csv URL.
     * Convert OOXML to a plain CSV ImportMetaPricingService can parse.
     */
    private function materializeImportCsv(string $body): string
    {
        $tempBase = tempnam(sys_get_temp_dir(), 'meta_usd_');
        if ($tempBase === false) {
            throw new RuntimeException('Unable to create temporary file for Meta rate card.');
        }

        $rawPath = $tempBase.'.bin';
        @unlink($tempBase);
        file_put_contents($rawPath, $body);

        try {
            if ($this->isZipContainer($body)) {
                $csvPath = $tempBase.'.csv';
                $this->xlsxToCsv($rawPath, $csvPath);
                @unlink($rawPath);

                return $csvPath;
            }

            $csvPath = $tempBase.'.csv';
            rename($rawPath, $csvPath);

            return $csvPath;
        } catch (Throwable $e) {
            @unlink($rawPath);
            throw $e;
        }
    }

    private function isZipContainer(string $body): bool
    {
        return str_starts_with($body, "PK\x03\x04") || str_starts_with($body, 'PK');
    }

    private function xlsxToCsv(string $xlsxPath, string $csvPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($xlsxPath) !== true) {
            throw new RuntimeException('Meta rate card looks like XLSX but could not be opened.');
        }

        try {
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml') ?: '';
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false || $sheetXml === '') {
                // Fall back to first worksheet entry.
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                        $sheetXml = (string) $zip->getFromIndex($i);
                        break;
                    }
                }
            }

            if (! is_string($sheetXml) || $sheetXml === '') {
                throw new RuntimeException('Meta XLSX rate card is missing a worksheet.');
            }

            $shared = $this->parseSharedStrings($sharedXml);
            $rows = $this->parseSheetRows($sheetXml, $shared);

            if ($rows === []) {
                throw new RuntimeException('Meta XLSX rate card contained no rows.');
            }

            $handle = fopen($csvPath, 'wb');
            if ($handle === false) {
                throw new RuntimeException('Unable to write temporary CSV for Meta rate card.');
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function parseSharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $shared = [];
        $sx = @simplexml_load_string($xml);
        if ($sx === false) {
            return [];
        }

        foreach ($sx->xpath('//*[local-name()="si"]') ?: [] as $si) {
            $texts = $si->xpath('.//*[local-name()="t"]') ?: [];
            $value = '';
            foreach ($texts as $t) {
                $value .= (string) $t;
            }
            $shared[] = $value;
        }

        return $shared;
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    private function parseSheetRows(string $sheetXml, array $shared): array
    {
        $sx = @simplexml_load_string($sheetXml);
        if ($sx === false) {
            throw new RuntimeException('Unable to parse Meta XLSX worksheet XML.');
        }

        $rows = [];

        foreach ($sx->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $cells = [];
            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $c) {
                $ref = (string) ($c['r'] ?? '');
                $col = $this->columnIndexFromCellRef($ref);
                if ($col < 0) {
                    continue;
                }

                $type = (string) ($c['t'] ?? '');
                $rawNodes = $c->xpath('./*[local-name()="v"]') ?: [];
                $raw = isset($rawNodes[0]) ? (string) $rawNodes[0] : '';
                if ($type === 's') {
                    $cells[$col] = $shared[(int) $raw] ?? '';
                } else {
                    $cells[$col] = $raw;
                }
            }

            if ($cells === []) {
                continue;
            }

            ksort($cells);
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = isset($cells[$i]) ? preg_replace("/\r\n|\n|\r/", ' ', (string) $cells[$i]) ?? '' : '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    private function columnIndexFromCellRef(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/i', $ref, $m)) {
            return -1;
        }

        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
