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
        $file = $this->makePdf(
            "GOVERNMENT OF INDIA\nGOODS AND SERVICES TAX\nGSTIN/UIN: 29ABCDE1234F1Z5\nLegal Name: Sharma Traders Private Limited\n"
        );

        $result = app(RegistrationCertificateService::class)->extractFromUpload($file);

        $this->assertTrue($result['valid']);
        $this->assertSame('29ABCDE1234F1Z5', $result['registration_number']);
        $this->assertSame('Sharma Traders Private Limited', $result['business_name']);
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
