<?php

declare(strict_types=1);

namespace Tests\Unit\Forms;

use App\Domains\Forms\Services\RegistrationPdfExtractor;
use Tests\TestCase;

class RegistrationPdfExtractorTest extends TestCase
{
    public function test_extracts_fields_from_reference_gst_reg06_certificate(): void
    {
        $path = base_path('tests/fixtures/gst_certificate_KAMMAR_UDYOG.pdf');
        $this->assertFileExists($path);

        $result = app(RegistrationPdfExtractor::class)->extract($path);

        $this->assertSame('GST', $result['document_type']);
        $this->assertSame('29AABFK4985D1Z5', $result['gstin']);
        $this->assertSame('29AABFK4985D1Z5', $result['registration_number']);
        $this->assertSame('KAMMAR UDYOG', $result['business_name']);
        $this->assertSame('AABFK4985D', $result['pan']);
        $this->assertSame('580024', $result['pincode']);
        $this->assertSame('Karnataka', $result['state']);
        $this->assertNotNull($result['business_address']);
        $this->assertStringContainsString('HUBLI', (string) $result['business_address']);
    }
}
