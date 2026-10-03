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

    public function test_sync_command_accepts_xlsx_payload_with_csv_url(): void
    {
        config(['services.whatsapp_meta_pricing.usd_csv_url' => 'https://example.test/meta-rates.csv']);

        Http::fake([
            'example.test/*' => Http::response($this->sampleXlsx(), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]),
        ]);

        $this->artisan('operations:sync-meta-pricing')
            ->assertSuccessful();

        $this->assertDatabaseHas('country_pricing', [
            'country_code' => 'IN',
            'currency' => 'USD',
        ], config('tenancy.database.central_connection'));
    }

    public function test_dry_run_does_not_persist_pricing_changes(): void
    {
        config(['services.whatsapp_meta_pricing.usd_csv_url' => 'https://example.test/meta-rates.csv']);

        Http::fake([
            'example.test/*' => Http::response($this->sampleCsv(), 200),
        ]);

        $this->artisan('operations:sync-meta-pricing', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('country_pricing', [
            'country_code' => 'IN',
            'marketing_price' => 0.1234,
        ], config('tenancy.database.central_connection'));
    }

    public function test_discover_extracts_csv_url_from_html(): void
    {
        $html = '<a href="https://l.facebook.com/l.php?u=https%3A%2F%2Fscontent.example.com%2Frates.csv">USD list rates</a>';
        $url = app(MetaWhatsAppUsdPricingSyncService::class)->extractUsdCsvUrlFromHtml($html);
        $this->assertSame('https://scontent.example.com/rates.csv', $url);

        $legacy = '<a href="https://l.facebook.com/l.php?u=https%3A%2F%2Fscontent.example.com%2Fold.csv">USD rates</a>';
        $legacyUrl = app(MetaWhatsAppUsdPricingSyncService::class)->extractUsdCsvUrlFromHtml($legacy);
        $this->assertSame('https://scontent.example.com/old.csv', $legacyUrl);
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

    private function sampleXlsx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'meta_xlsx_');
        $this->assertNotFalse($path);
        $xlsxPath = $path.'.xlsx';
        @unlink($path);

        $sharedStrings = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="6" uniqueCount="6">
  <si><t>Market</t></si>
  <si><t>Marketing</t></si>
  <si><t>Utility</t></si>
  <si><t>Authentication</t></si>
  <si><t>Service</t></si>
  <si><t>India</t></si>
</sst>
XML;

        $sheet = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      <c r="A1" t="s"><v>0</v></c>
      <c r="B1" t="s"><v>1</v></c>
      <c r="C1" t="s"><v>2</v></c>
      <c r="D1" t="s"><v>3</v></c>
      <c r="E1" t="s"><v>4</v></c>
    </row>
    <row r="2">
      <c r="A2" t="s"><v>5</v></c>
      <c r="B2"><v>0.1234</v></c>
      <c r="C2"><v>0.0567</v></c>
      <c r="D2"><v>0.0333</v></c>
      <c r="E2"><v>0</v></c>
    </row>
  </sheetData>
</worksheet>
XML;

        $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML;

        $rels = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;

        $workbook = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="List rates" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML;

        $workbookRels = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML;

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($xlsxPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/sharedStrings.xml', $sharedStrings);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $binary = file_get_contents($xlsxPath);
        @unlink($xlsxPath);
        $this->assertNotFalse($binary);

        return $binary;
    }
}
