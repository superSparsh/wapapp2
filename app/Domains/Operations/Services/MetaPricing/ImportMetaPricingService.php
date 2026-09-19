<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services\MetaPricing;

use App\Models\CountryPricing;
use App\Models\CountryPricingLog;
use Illuminate\Support\Facades\DB;

class ImportMetaPricingService
{
    protected string $pricingCurrency = 'USD';

    protected ?string $auditBatchId = null;

    protected string $auditSource = 'meta_sync';

    protected ?int $updatedBy = null;

    /** @var array{updated: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: list<array<string, mixed>>} */
    protected array $results = [
        'updated' => [],
        'skipped' => [],
        'errors' => [],
    ];

    /** @var array<string, string> */
    protected array $countryMapping = [
        'United Arab' => 'United Arab Emirates',
        'United King' => 'United Kingdom',
        'North Ame' => 'North America',
        'Rest of Afri' => 'Rest of Africa',
        'Rest of Asi' => 'Rest of Asia Pacific',
        'Rest of Asia' => 'Rest of Asia Pacific',
        'Rest of Cer' => 'Rest of Central & Eastern Europe',
        'Rest of Lat' => 'Rest of Latin America',
        'Rest of Mic' => 'Rest of Middle East',
        'Rest of We' => 'Rest of Western Europe',
    ];

    /** @var array<string, true> */
    protected array $directlyUpdatedCodes = [];

    protected ?MetaMarketRegionService $marketRegions = null;

    public function setPricingCurrency(string $currency): self
    {
        $this->pricingCurrency = strtoupper($currency) === 'INR' ? 'INR' : 'USD';

        return $this;
    }

    public function setAuditContext(?string $batchId, string $source = 'meta_sync', ?int $updatedBy = null): self
    {
        $this->auditBatchId = $batchId;
        $this->auditSource = $source;
        $this->updatedBy = $updatedBy;

        return $this;
    }

    /**
     * @return array{updated: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: list<array<string, mixed>>}
     */
    public function importFromCsv(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new \RuntimeException('CSV file not found');
        }

        $csvData = $this->parseCsv($filePath);

        if ($csvData === []) {
            throw new \RuntimeException('CSV file is empty or invalid');
        }

        $this->validateCsvStructure($csvData);

        $this->directlyUpdatedCodes = [];
        $this->marketRegions = new MetaMarketRegionService;
        $regionalRows = [];

        DB::connection((new CountryPricing)->getConnectionName())->transaction(function () use ($csvData, &$regionalRows): void {
            foreach ($csvData as $index => $row) {
                if (empty(trim((string) ($row['Market'] ?? '')))) {
                    continue;
                }

                $marketName = trim((string) $row['Market']);
                $lineNumber = $index + 2;

                if ($this->marketRegions->isRegionalCsvMarket($marketName)) {
                    $regionalRows[$marketName] = ['row' => $row, 'line' => $lineNumber];

                    continue;
                }

                $this->processRow($row, $lineNumber);
            }

            $this->applyRegionalRows($regionalRows);

            if ($this->pricingCurrency === 'USD') {
                CountryPricing::query()
                    ->whereNotIn('currency', ['USD', '$'])
                    ->update(['currency' => 'USD', 'updated_at' => now()]);
            }
        });

        return $this->results;
    }

    /**
     * @return list<array<string, string>>
     */
    protected function parseCsv(string $filePath): array
    {
        $data = [];
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV file');
        }

        $headers = null;
        while (($row = fgetcsv($handle)) !== false) {
            $trimmed = array_map(static fn ($h) => trim((string) $h), $row);
            if (in_array('Market', $trimmed, true)) {
                $headers = array_map(static function ($h) {
                    return preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', trim((string) $h))) ?? '';
                }, $trimmed);
                break;
            }
        }

        if ($headers === null) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or missing Market header row');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row) ?: [];
            } elseif (count($row) > count($headers)) {
                $combined = array_combine($headers, array_slice($row, 0, count($headers)));
                if ($combined !== false) {
                    $data[] = $combined;
                }
            }
        }

        fclose($handle);

        return $data;
    }

    /**
     * @param  list<array<string, string>>  $csvData
     */
    protected function validateCsvStructure(array $csvData): void
    {
        $requiredColumns = ['Market', 'Marketing', 'Utility', 'Authentication'];
        $firstRow = $csvData[0];
        $missingColumns = [];

        foreach ($requiredColumns as $column) {
            if (! array_key_exists($column, $firstRow)) {
                $missingColumns[] = $column;
            }
        }

        if ($missingColumns !== []) {
            throw new \RuntimeException('Missing required columns: '.implode(', ', $missingColumns));
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function processRow(array $row, int $lineNumber): void
    {
        $marketName = trim((string) ($row['Market'] ?? ''));
        if ($marketName === '') {
            return;
        }

        $country = $this->findOrCreateCountry($marketName);

        if (! $country) {
            $this->results['skipped'][] = [
                'line' => $lineNumber,
                'market' => $marketName,
                'reason' => 'Country not found in database',
            ];

            return;
        }

        $newPricing = [
            'marketing_price' => $this->parsePrice($row['Marketing'] ?? null),
            'utility_price' => $this->parsePrice($row['Utility'] ?? null),
            'auth_price' => $this->parsePrice($row['Authentication'] ?? null),
            'auth_international_price' => $this->parsePriceByColumn($row, [
                'Authentication- International',
                'Authentication International',
                'Authentic',
            ]),
            'service_price' => $this->parsePrice($row['Service'] ?? null),
        ];

        $before = count($this->results['updated']);
        $this->updateCountryPricing($country, $newPricing, $lineNumber, $marketName);
        if (count($this->results['updated']) > $before) {
            $this->directlyUpdatedCodes[strtoupper((string) $country->country_code)] = true;
        }
    }

    /**
     * @param  array<string, array{row: array<string, string>, line: int}>  $regionalRows
     */
    protected function applyRegionalRows(array $regionalRows): void
    {
        foreach ($this->marketRegions->regionalCsvNames() as $marketName) {
            if (! isset($regionalRows[$marketName])) {
                continue;
            }

            $payload = $regionalRows[$marketName];
            $this->applyRegionalMarket($marketName, $payload['row'], $payload['line']);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function applyRegionalMarket(string $marketName, array $row, int $lineNumber): void
    {
        $newPricing = [
            'marketing_price' => $this->parsePrice($row['Marketing'] ?? null),
            'utility_price' => $this->parsePrice($row['Utility'] ?? null),
            'auth_price' => $this->parsePrice($row['Authentication'] ?? null),
            'auth_international_price' => $this->parsePriceByColumn($row, [
                'Authentication- International',
                'Authentication International',
                'Authentic',
            ]),
            'service_price' => $this->parsePrice($row['Service'] ?? null),
        ];

        $countryCodes = $this->marketRegions->countryCodesForRegionalMarket($marketName);

        if ($marketName === 'Other') {
            $map = $this->marketRegions->buildCodeToMarketMap();
            $countryCodes = [];
            foreach (CountryPricing::query()->pluck('country_code') as $code) {
                $upper = strtoupper((string) $code);
                if (isset($this->directlyUpdatedCodes[$upper])) {
                    continue;
                }
                if (($map[$upper] ?? 'Other') === 'Other') {
                    $countryCodes[] = $upper;
                }
            }
        }

        foreach ($countryCodes as $code) {
            if (isset($this->directlyUpdatedCodes[$code])) {
                continue;
            }

            $country = CountryPricing::query()->where('country_code', $code)->first();
            if (! $country) {
                continue;
            }

            $this->updateCountryPricing(
                $country,
                $newPricing,
                $lineNumber,
                $marketName.' (regional bucket)'
            );
        }
    }

    /**
     * @param  array<string, float|null>  $newPricing
     */
    protected function updateCountryPricing(
        CountryPricing $country,
        array $newPricing,
        int $lineNumber,
        string $marketLabel
    ): void {
        $hasChanges = false;
        $changes = [];

        foreach ($newPricing as $field => $newValue) {
            if ($newValue === null) {
                continue;
            }

            $oldFloat = $country->{$field} === null || $country->{$field} === ''
                ? null
                : round((float) $country->{$field}, 4);
            $newFloat = round((float) $newValue, 4);

            if ($oldFloat !== $newFloat) {
                $hasChanges = true;
                $changes[$field] = ['old' => $country->{$field}, 'new' => $newValue];
            }
        }

        if (! $hasChanges) {
            if ($this->pricingCurrency === 'USD' && ! in_array($country->currency, ['USD', '$'], true)) {
                $country->currency = 'USD';
                $country->save();
            }

            $this->results['skipped'][] = [
                'line' => $lineNumber,
                'market' => $marketLabel,
                'country' => $country->country_name,
                'reason' => 'No pricing changes detected',
            ];

            return;
        }

        $oldSnapshot = [];
        foreach (array_keys($newPricing) as $field) {
            $oldSnapshot[$field] = $country->{$field};
        }

        foreach ($newPricing as $field => $value) {
            if ($value !== null) {
                $country->{$field} = $value;
            }
        }

        $country->currency = $this->pricingCurrency === 'INR' ? '₹' : 'USD';
        $country->status = 1;
        $country->save();

        $this->logPriceChanges($country, $oldSnapshot, $newPricing);

        $this->results['updated'][] = [
            'line' => $lineNumber,
            'market' => $marketLabel,
            'country_id' => $country->id,
            'country' => $country->country_name,
            'changes' => $changes,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldSnapshot
     * @param  array<string, float|null>  $newPricing
     */
    protected function logPriceChanges(
        CountryPricing $country,
        array $oldSnapshot,
        array $newPricing,
    ): void {
        if (blank($country->country_code)) {
            return;
        }

        $fields = CountryPricing::priceFields();

        foreach ($newPricing as $column => $newValue) {
            if ($newValue === null || ! isset($fields[$column])) {
                continue;
            }

            $old = $oldSnapshot[$column] ?? null;
            if ((string) $old === (string) $newValue) {
                continue;
            }

            CountryPricingLog::query()->create([
                'country_code' => (string) $country->country_code,
                'conversation' => $fields[$column],
                'old_price' => $old,
                'new_price' => $newValue,
                'updated_by' => $this->updatedBy ?? 0,
            ]);
        }
    }

    protected function findOrCreateCountry(string $marketName): ?CountryPricing
    {
        $country = $this->findCountry($marketName);
        if ($country) {
            return $country;
        }

        $code = $this->marketRegions?->countryCodeForDirectMarket($marketName);
        if ($code === null) {
            $mapped = $this->countryMapping[$marketName] ?? null;
            if ($mapped) {
                $code = $this->marketRegions?->countryCodeForDirectMarket($mapped);
            }
        }

        if ($code === null) {
            return null;
        }

        return CountryPricing::query()->create([
            'country_code' => $code,
            'country_name' => $this->countryMapping[$marketName] ?? $marketName,
            'currency' => $this->pricingCurrency === 'INR' ? '₹' : 'USD',
            'status' => 1,
        ]);
    }

    protected function findCountry(string $marketName): ?CountryPricing
    {
        $country = CountryPricing::query()->where('country_name', $marketName)->first();
        if ($country) {
            return $country;
        }

        if (isset($this->countryMapping[$marketName])) {
            $mappedName = $this->countryMapping[$marketName];
            $country = CountryPricing::query()->where('country_name', $mappedName)->first();
            if ($country) {
                return $country;
            }
        }

        $code = $this->marketRegions?->countryCodeForDirectMarket($marketName);
        if ($code) {
            $country = CountryPricing::query()->where('country_code', $code)->first();
            if ($country) {
                return $country;
            }
        }

        return CountryPricing::query()->where('country_name', 'LIKE', $marketName.'%')->first();
    }

    protected function parsePrice(?string $value): ?float
    {
        if ($value === null || trim($value) === '' || strtolower(trim($value)) === 'n/a') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.]/', '', $value);

        return $cleaned !== '' && $cleaned !== null ? round((float) $cleaned, 4) : null;
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $preferredKeys
     */
    protected function parsePriceByColumn(array $row, array $preferredKeys): ?float
    {
        foreach ($preferredKeys as $key) {
            if (array_key_exists($key, $row)) {
                return $this->parsePrice($row[$key]);
            }
        }

        foreach ($row as $column => $value) {
            $normalized = preg_replace('/\s+/', ' ', strtolower(str_replace(["\r", "\n"], ' ', (string) $column))) ?? '';
            if (str_contains($normalized, 'authentication') && str_contains($normalized, 'international')) {
                return $this->parsePrice($value);
            }
        }

        return null;
    }
}
