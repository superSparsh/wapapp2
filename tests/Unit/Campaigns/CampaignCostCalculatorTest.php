<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Domains\Campaigns\Services\CampaignCostCalculator;
use App\Models\CountryPricing;
use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_cost_uses_admin_usd_price_times_conversion_rate(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'wallet.conversion_price'],
            ['value' => '80'],
        );

        CountryPricing::query()->create([
            'country_code' => 'IN',
            'country_name' => 'India',
            'currency' => 'USD',
            'marketing_price' => 0.01,
            'utility_price' => 0.005,
            'auth_price' => 0.004,
            'status' => 1,
        ]);

        $calculator = app(CampaignCostCalculator::class);

        $this->assertSame(0.8, $calculator->unitCostForCategory('MARKETING'));
        $this->assertSame(0.4, $calculator->unitCostForCategory('UTILITY'));

        $estimate = $calculator->estimateFor(100, 'MARKETING');
        $this->assertSame(100, $estimate['recipients']);
        $this->assertSame(0.8, $estimate['unit_cost']);
        $this->assertSame(80.0, $estimate['total_cost']);
        $this->assertSame('INR', $estimate['currency']);
    }

    public function test_prefers_meta_price_then_tekpro_fallback(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'wallet.conversion_price'],
            ['value' => '100'],
        );

        CountryPricing::query()->create([
            'country_code' => 'IN',
            'country_name' => 'India',
            'currency' => 'USD',
            'marketing_price' => 0.01,
            'tekpro_marketing_price' => 0.02,
            'status' => 1,
        ]);

        $calculator = app(CampaignCostCalculator::class);

        // Meta USD 0.01 × 100 = 1.0 (tekpro ignored when meta exists)
        $this->assertSame(1.0, $calculator->unitCostForCategory('MARKETING'));
    }

    public function test_inr_currency_skips_conversion_multiply(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'wallet.conversion_price'],
            ['value' => '80'],
        );

        CountryPricing::query()->create([
            'country_code' => 'IN',
            'country_name' => 'India',
            'currency' => 'INR',
            'marketing_price' => 0.95,
            'status' => 1,
        ]);

        $calculator = app(CampaignCostCalculator::class);

        $this->assertSame(0.95, $calculator->unitCostForCategory('MARKETING'));
    }

    public function test_conversion_price_reader(): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => 'wallet.conversion_price'],
            ['value' => '84.5'],
        );

        $this->assertSame(84.5, app(CampaignCostCalculator::class)->conversionPrice());
        $this->assertSame('84.5', app(PlatformSettingsService::class)->get('wallet.conversion_price'));
    }
}
