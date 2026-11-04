<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use App\Domains\Admin\Services\RegistrationCertificateService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegistrationCertificateServiceTest extends TestCase
{
    private function makePdf(string $bodyText, string $name = 'cert.pdf'): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\n".$bodyText."\n%%EOF\n";
        $path = tempnam(sys_get_temp_dir(), 'gstpdf_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    public function test_accepts_gst_certificate_and_extracts_fields(): void
    {
        $path = base_path('tests/fixtures/gst_certificate_KAMMAR_UDYOG.pdf');
        $this->assertFileExists($path);
        $file = new UploadedFile($path, 'gst_certificate_KAMMAR_UDYOG.pdf', 'application/pdf', null, true);

        $result = app(RegistrationCertificateService::class)->extractFromUpload($file);

        $this->assertTrue($result['valid']);
        $this->assertSame('29AABFK4985D1Z5', $result['registration_number']);
        $this->assertSame('KAMMAR UDYOG', $result['business_name']);
        $this->assertSame('GST certificate', $result['doc_type']);
    }

    public function test_accepts_microsoft_print_to_pdf_gst_certificate(): void
    {
        $path = base_path('tests/fixtures/gst_certificate_MEYDAN_INFRA_print.pdf');
        $this->assertFileExists($path);
        $file = new UploadedFile($path, 'gst_certificate_MEYDAN_INFRA_print.pdf', 'application/pdf', null, true);

        $result = app(RegistrationCertificateService::class)->extractFromUpload($file);

        $this->assertTrue($result['valid']);
        $this->assertSame('03ABYFM7945F1ZQ', $result['registration_number']);
        $this->assertSame('MEYDAN INFRA LLP', $result['business_name']);
        $this->assertSame('GST certificate', $result['doc_type']);
    }

    public function test_rejects_non_gst_msme_pdf(): void
    {
        $file = $this->makePdf("Invoice\nAmount: 1000\nCustomer: Someone\n");

        $result = app(RegistrationCertificateService::class)->extractFromUpload($file);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('GST or Udyam', $result['message']);
    }

    public function test_rejects_non_pdf_extension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notpdf_');
        file_put_contents($path, "%PDF-1.4\nfake\n");
        $file = new UploadedFile($path, 'cert.exe.pdf', 'application/pdf', null, true);

        // Name contains .exe — blocked by security check
        $result = app(RegistrationCertificateService::class)->extractFromUpload($file);
        $this->assertFalse($result['valid']);
    }
}
