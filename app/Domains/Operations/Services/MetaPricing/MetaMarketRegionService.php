<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services\MetaPricing;

use App\Models\CountryPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetaMarketRegionService
{
    /** @var array<string, string>|null */
    protected ?array $codeToMarket = null;

    /** @var array<string, string>|null */
    protected ?array $overrides = null;

    /**
     * @return list<string>
     */
    public function regionalCsvNames(): array
    {
        return config('meta_whatsapp_markets.regional_csv_names', []);
    }

    public function isRegionalCsvMarket(string $marketName): bool
    {
        return in_array(trim($marketName), $this->regionalCsvNames(), true);
    }

    public function isDirectCountryCode(string $countryCode): bool
    {
        $code = strtoupper($countryCode);

        return isset(config('meta_whatsapp_markets.direct_country_codes', [])[$code]);
    }

    /**
     * @return array<string, string> ISO code => Meta CSV market name
     */
    public function buildCodeToMarketMap(bool $includeDatabase = true): array
    {
        if ($includeDatabase && $this->codeToMarket !== null) {
            return $this->codeToMarket;
        }

        $map = $this->buildCodeToMarketMapFromConfig();

        if ($includeDatabase && Schema::hasTable('country_meta_market')) {
            foreach (DB::table('country_meta_market')->get() as $row) {
                $map[strtoupper((string) $row->country_code)] = (string) $row->meta_market;
            }
        }

        if ($includeDatabase) {
            $this->codeToMarket = $map;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public function buildCodeToMarketMapFromConfig(): array
    {
        $map = [];

        foreach ($this->loadOverrides() as $code => $market) {
            $map[strtoupper((string) $code)] = (string) $market;
        }

        foreach (config('meta_whatsapp_markets.direct_country_codes', []) as $code => $market) {
            $map[strtoupper((string) $code)] = (string) $market;
        }

        $regionKeys = [
            'north_america' => 'North America',
            'rest_of_africa' => 'Rest of Africa',
            'rest_of_asia_pacific' => 'Rest of Asia Pacific',
            'rest_of_central_eastern_europe' => 'Rest of Central & Eastern Europe',
            'rest_of_latin_america' => 'Rest of Latin America',
            'rest_of_middle_east' => 'Rest of Middle East',
            'rest_of_western_europe' => 'Rest of Western Europe',
        ];

        foreach ($regionKeys as $configKey => $csvName) {
            foreach (config('meta_whatsapp_markets.'.$configKey, []) as $code) {
                $upper = strtoupper((string) $code);
                if (! isset($map[$upper])) {
                    $map[$upper] = $csvName;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public function loadOverrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        $path = database_path('data/meta_market_overrides.php');
        $this->overrides = is_file($path) ? (array) require $path : [];

        return $this->overrides;
    }

    /**
     * @return list<string>
     */
    public function countryCodesForRegionalMarket(string $csvMarketName): array
    {
        $map = $this->buildCodeToMarketMap();
        $codes = [];

        foreach ($map as $code => $market) {
            if ($market === $csvMarketName) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * Resolve ISO code for a direct Meta CSV market name (e.g. India → IN).
     */
    public function countryCodeForDirectMarket(string $marketName): ?string
    {
        $needle = trim($marketName);
        foreach (config('meta_whatsapp_markets.direct_country_codes', []) as $code => $name) {
            if (strcasecmp((string) $name, $needle) === 0) {
                return strtoupper((string) $code);
            }
        }

        return null;
    }
}
