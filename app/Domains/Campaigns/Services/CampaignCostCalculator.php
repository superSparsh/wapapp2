<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Models\Campaign;
use App\Models\CountryPricing;

/**
 * Estimates campaign cost from admin country pricing (USD) × wallet conversion rate (INR/USD).
 */
class CampaignCostCalculator
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * Estimate template message cost for a campaign.
     *
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string, category: string}
     */
    public function estimate(Campaign $campaign): array
    {
        return $this->estimateFor(
            max(0, (int) $campaign->total_recipients),
            $campaign->template?->category,
        );
    }

    /**
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string, category: string}
     */
    public function estimateFor(int $recipients, ?string $category, ?string $countryCode = null): array
    {
        $recipients = max(0, $recipients);
        $normalized = strtoupper(trim((string) $category));
        $unitCost = $this->unitCostForCategory($normalized, $countryCode);
        $currency = (string) config('campaigns.cost.currency', 'INR');

        return [
            'recipients' => $recipients,
            'unit_cost' => $unitCost,
            'total_cost' => round($unitCost * $recipients, 2),
            'currency' => $currency,
            'category' => $normalized !== '' ? $normalized : 'DEFAULT',
        ];
    }

    public function unitCostForCategory(?string $category, ?string $countryCode = null): float
    {
        $normalized = strtoupper(trim((string) $category));
        $rates = $this->categoryRatesInr($countryCode);

        if ($normalized !== '' && isset($rates[$normalized])) {
            return (float) $rates[$normalized];
        }

        return (float) ($rates['DEFAULT'] ?? 0.0);
    }

    /**
     * INR per-message rates by template category for the estimate country.
     *
     * @return array<string, float>
     */
    public function categoryRatesInr(?string $countryCode = null): array
    {
        $pricing = $this->resolveCountryPricing($countryCode);
        $conversion = $this->conversionPrice();
        $fallback = (array) config('campaigns.cost.category_rates', []);

        $marketing = $this->inrUnitCost($pricing, 'MARKETING', $conversion, $fallback);
        $utility = $this->inrUnitCost($pricing, 'UTILITY', $conversion, $fallback);
        $auth = $this->inrUnitCost($pricing, 'AUTHENTICATION', $conversion, $fallback);
        $default = $marketing > 0 ? $marketing : (float) ($fallback['DEFAULT'] ?? 0.78);

        return [
            'MARKETING' => $marketing,
            'UTILITY' => $utility,
            'AUTHENTICATION' => $auth,
            'DEFAULT' => $default,
        ];
    }

    public function conversionPrice(): float
    {
        $raw = $this->platformSettings->get(
            'wallet.conversion_price',
            (string) config('campaigns.cost.fallback_conversion_price', '83.17'),
        );

        $value = (float) $raw;

        return $value > 0 ? $value : 83.17;
    }

    private function inrUnitCost(
        ?CountryPricing $pricing,
        string $category,
        float $conversion,
        array $fallback,
    ): float {
        if ($pricing instanceof CountryPricing) {
            $raw = $this->rawCategoryPrice($pricing, $category);
            if ($raw !== null && $raw > 0) {
                return round($this->toInr($raw, $pricing, $conversion), 4);
            }
        }

        return round((float) ($fallback[$category] ?? $fallback['DEFAULT'] ?? 0), 4);
    }

    private function rawCategoryPrice(CountryPricing $pricing, string $category): ?float
    {
        [$tekproField, $metaField] = match ($category) {
            'MARKETING' => ['tekpro_marketing_price', 'marketing_price'],
            'UTILITY' => ['tekpro_utility_price', 'utility_price'],
            'AUTHENTICATION' => ['tekpro_auth_price', 'auth_price'],
            default => [null, null],
        };

        if ($metaField !== null) {
            $meta = $pricing->{$metaField};
            if ($meta !== null && (float) $meta > 0) {
                return (float) $meta;
            }
        }

        if ($tekproField !== null) {
            $tekpro = $pricing->{$tekproField};
            if ($tekpro !== null && (float) $tekpro > 0) {
                return (float) $tekpro;
            }
        }

        return null;
    }

    private function toInr(float $rawPrice, CountryPricing $pricing, float $conversion): float
    {
        $currency = strtoupper(trim((string) ($pricing->currency ?? 'USD')));
        $currency = str_replace(['₹', 'RS.', 'RS'], ['INR', 'INR', 'INR'], $currency);

        // Already INR — do not apply FX again.
        if (in_array($currency, ['INR', 'INDIAN RUPEE', 'INDIAN RUPEES'], true)) {
            return $rawPrice;
        }

        // Meta USD (and any non-INR currency) × admin conversion price.
        return $rawPrice * max(0.0, $conversion);
    }

    private function resolveCountryPricing(?string $countryCode = null): ?CountryPricing
    {
        $code = strtoupper(trim((string) (
            $countryCode
            ?: config('campaigns.cost.default_country_code', 'IN')
        )));

        $query = CountryPricing::query()->where('status', 1);

        $match = (clone $query)->where('country_code', $code)->first();
        if ($match instanceof CountryPricing) {
            return $match;
        }

        $byName = (clone $query)->where('country_name', 'India')->first();
        if ($byName instanceof CountryPricing) {
            return $byName;
        }

        return $query->orderBy('country_name')->first();
    }
}
