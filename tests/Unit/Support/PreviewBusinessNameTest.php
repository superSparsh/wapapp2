<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\WhatsappLine;
use App\Support\PreviewBusinessName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class PreviewBusinessNameTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_uses_default_whatsapp_line_display_name(): void
    {
        $this->testLine->forceFill(['display_name' => 'Acme Support'])->save();

        $this->assertSame('Acme Support', PreviewBusinessName::resolve());
    }

    public function test_falls_back_to_tenant_company_name_when_line_has_no_display_name(): void
    {
        $this->testLine->forceFill(['display_name' => null])->save();
        $this->testTenant->forceFill(['company_name' => 'Demo Company Pvt Ltd'])->save();

        $this->assertSame('Demo Company Pvt Ltd', PreviewBusinessName::resolve());
    }

    public function test_falls_back_to_your_business_when_no_name_available(): void
    {
        $this->testLine->delete();
        $this->testTenant->forceFill(['company_name' => null])->save();

        $this->assertSame('Your Business', PreviewBusinessName::resolve());
    }
}
