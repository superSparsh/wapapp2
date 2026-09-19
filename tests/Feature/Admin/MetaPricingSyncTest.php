<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domains\Operations\Services\MetaPricing\ImportMetaPricingService;
use App\Domains\Operations\Services\MetaPricing\MetaWhatsAppUsdPricingSyncService;
use App\Models\Admin;
use App\Models\CountryPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MetaPricingSyncTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Pricing Admin',
            'email' => 'pricing-admin@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_import_creates_missing_direct_market_and_updates_prices(): void
    {
        $path = $this->writeTempCsv($this->sampleCsv());

        $results = (new ImportMetaPricingService)
            ->setPricingCurrency('USD')
            ->setAuditContext('batch-test', 'meta_sync', (int) $this->admin->id)
            ->importFromCsv($path);

        @unlink($path);

        $this->assertNotEmpty($results['updated']);
        $india = CountryPricing::query()->where('country_code', 'IN')->first();
        $this->assertNotNull($india);
        $this->assertSame('India', $india->country_name);
        $this->assertEquals(0.1234, (float) $india->marketing_price);
        $this->assertEquals(0.0567, (float) $india->utility_price);
        $this->assertEquals('USD', $india->currency);
    }

    public function test_import_skips_when_prices_unchanged(): void
    {
        CountryPricing::query()->create([
            'country_code' => 'IN',
            'country_name' => 'India',
            'currency' => 'USD',
            'marketing_price' => 0.1234,
            'utility_price' => 0.0567,
            'auth_price' => 0.0333,
            'service_price' => 0.0,
            'status' => 1,
        ]);

        $path = $this->writeTempCsv($this->sampleCsv());

        $results = (new ImportMetaPricingService)
            ->setPricingCurrency('USD')
            ->importFromCsv($path);

        @unlink($path);

        $this->assertSame([], $results['updated']);
        $this->assertNotEmpty($results['skipped']);
        $this->assertSame('No pricing changes detected', $results['skipped'][0]['reason'] ?? null);
    }

    public function test_sync_command_downloads_csv_via_http_fake(): void
    {
        config(['services.whatsapp_meta_pricing.usd_csv_url' => 'https://example.test/meta-rates.csv']);

        Http::fake([
            'example.test/*' => Http::response($this->sampleCsv(), 200),
        ]);

        $this->artisan('operations:sync-meta-pricing')
            ->assertSuccessful();

        $this->assertDatabaseHas('country_pricing', [
            'country_code' => 'IN',
            'currency' => 'USD',
        ], config('tenancy.database.central_connection'));
    }

    public function test_discover_extracts_csv_url_from_html(): void
    {
        $html = '<a href="https://l.facebook.com/l.php?u=https%3A%2F%2Fscontent.example.com%2Frates.csv">USD rates</a>';
        $url = app(MetaWhatsAppUsdPricingSyncService::class)->extractUsdCsvUrlFromHtml($html);
        $this->assertSame('https://scontent.example.com/rates.csv', $url);
    }

    public function test_admin_auto_fetch_updates_pricing(): void
    {
        config(['services.whatsapp_meta_pricing.usd_csv_url' => 'https://example.test/meta-rates.csv']);

        Http::fake([
            'example.test/*' => Http::response($this->sampleCsv(), 200),
        ]);

        $this->actingAs($this->admin, 'admin')
            ->from(route('admin.pricing.index'))
            ->post(route('admin.pricing.sync-meta'))
            ->assertRedirect(route('admin.pricing.index'));

        $india = CountryPricing::query()->where('country_code', 'IN')->first();
        $this->assertNotNull($india);
        $this->assertEquals(0.1234, (float) $india->marketing_price);
    }

    public function test_regional_row_skipped_when_member_country_missing(): void
    {
        $csv = <<<'CSV'
Preamble

Market,Marketing,Utility,Authentication,Service
Rest of Africa,0.01,0.02,0.03,0
CSV;
        $path = $this->writeTempCsv($csv);

        $results = (new ImportMetaPricingService)
            ->setPricingCurrency('USD')
            ->importFromCsv($path);

        @unlink($path);

        $this->assertSame([], $results['updated']);
        $this->assertSame(0, CountryPricing::query()->where('country_code', 'KE')->count());
    }

    private function writeTempCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'meta_test_');
        $this->assertNotFalse($path);
        $csvPath = $path.'.csv';
        rename($path, $csvPath);
        file_put_contents($csvPath, $contents);

        return $csvPath;
    }

    private function sampleCsv(): string
    {
        return <<<'CSV'
WhatsApp pricing

Market,Marketing,Utility,Authentication,Service
India,0.1234,0.0567,0.0333,0
CSV;
    }
}
