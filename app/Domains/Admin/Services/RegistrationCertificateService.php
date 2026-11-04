<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Forms\Services\RegistrationPdfExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Strict GST / Udyam (MSME) certificate PDF validation + field extraction
 * for customer readiness. Rejects anything that is not a recognizable
 * registration certificate.
 */
class RegistrationCertificateService
{
    private const MAX_BYTES = 5_242_880; // 5 MB

    public function __construct(
        private readonly RegistrationPdfExtractor $pdfExtractor,
    ) {}

    /**
     * @return array{
     *     valid: bool,
     *     message: string,
     *     business_name: ?string,
     *     registration_number: ?string,
     *     doc_type: ?string,
     *     document_type: ?string
     * }
     */
    public function extractFromUpload(UploadedFile $file): array
    {
        $fail = fn (string $message): array => [
            'valid' => false,
            'message' => $message,
            'business_name' => null,
            'registration_number' => null,
            'doc_type' => null,
            'document_type' => null,
        ];

        $security = $this->assertSecurePdf($file);
        if ($security !== null) {
            return $fail($security);
        }

        $absolutePath = $file->getRealPath() ?: $file->getPathname();
        if ($absolutePath === '' || ! is_file($absolutePath)) {
            return $fail('We could not read the uploaded PDF. Please try again.');
        }

        try {
            $extracted = $this->pdfExtractor->extract($absolutePath);
        } catch (\Throwable $e) {
            Log::notice('Registration certificate extract failed', [
                'error' => $e->getMessage(),
                'original_name' => $file->getClientOriginalName(),
            ]);

            return $fail('We could not read this PDF. Upload a clear GST or Udyam (MSME) certificate.');
        }

        $documentType = strtoupper((string) ($extracted['document_type'] ?? ''));
        if (! in_array($documentType, ['GST', 'UDYAM'], true)) {
            $hasOcrTools = $this->ocrToolsAvailable();
            if (! $hasOcrTools) {
                return $fail(
                    'This PDF has no readable text layer (common with “Microsoft Print to PDF”). Install poppler-utils + tesseract on the server, or upload the original GST / Udyam certificate PDF.'
                );
            }

            return $fail(
                'This does not look like a GST or Udyam MSME certificate. Only those two documents are accepted — we can’t onboard you with other PDFs.'
            );
        }

        $registrationNumber = strtoupper(trim((string) (
            $extracted['registration_number']
            ?? ($documentType === 'UDYAM' ? ($extracted['udyam'] ?? '') : ($extracted['gstin'] ?? ''))
        )));

        $gstinRegex = (string) config(
            'customer-readiness.gstin_regex',
            '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'
        );
        $udyamRegex = (string) config(
            'customer-readiness.udyam_regex',
            '/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/'
        );

        if ($documentType === 'GST') {
            if (preg_match($gstinRegex, $registrationNumber) !== 1) {
                return $fail(
                    'We found a GST certificate, but could not read a valid GSTIN from it. Upload a clear official GST certificate PDF.'
                );
            }
            $docType = 'GST certificate';
        } else {
            if (preg_match($udyamRegex, $registrationNumber) !== 1) {
                return $fail(
                    'We found an MSME/Udyam certificate, but could not read a valid Udyam number from it. Upload a clear official Udyam certificate PDF.'
                );
            }
            $docType = 'Udyam MSME certificate';
        }

        $businessName = trim((string) ($extracted['business_name'] ?? ''));
        $businessName = (string) preg_replace('/\s+/', ' ', $businessName);
        // Strip common PDF trailer / object noise that raw text extraction can glue on.
        $businessName = (string) preg_replace('/\s*%%EOF.*/i', '', $businessName);
        $businessName = (string) preg_replace('/\s*\b(endobj|endstream|trailer|xref)\b.*/i', '', $businessName);
        $businessName = trim($businessName, " \t\n\r\0\x0B:-");
        if ($businessName === '' || Str::length($businessName) < 2) {
            return $fail(
                'We could not read the business / legal name from this certificate. Upload a clearer GST or Udyam PDF — we can’t onboard you without it.'
            );
        }

        // Soft guard against garbage OCR / binary junk names.
        if (preg_match('/^[\x20-\x7E\x{0900}-\x{097F}]+$/u', $businessName) !== 1) {
            return $fail(
                'The business name read from this PDF looks invalid. Please upload the official certificate PDF.'
            );
        }

        return [
            'valid' => true,
            'message' => 'Certificate verified. GST / MSME details extracted.',
            'business_name' => $businessName,
            'registration_number' => $registrationNumber,
            'doc_type' => $docType,
            'document_type' => $documentType,
        ];
    }

    private function assertSecurePdf(UploadedFile $file): ?string
    {
        if (! $file->isValid()) {
            return 'The upload failed. Please choose your GST or Udyam certificate PDF again.';
        }

        if ($file->getSize() <= 0 || $file->getSize() > self::MAX_BYTES) {
            return 'PDF must be under 5 MB.';
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension !== 'pdf') {
            return 'Only PDF files are allowed (GST or Udyam MSME certificate).';
        }

        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));
        $allowedMimes = ['application/pdf', 'application/x-pdf', 'application/acrobat'];
        if (! in_array($mime, $allowedMimes, true)) {
            return 'Only PDF files are allowed. This file type is not accepted.';
        }

        $path = $file->getRealPath() ?: $file->getPathname();
        $header = is_string($path) && is_file($path)
            ? (string) @file_get_contents($path, false, null, 0, 5)
            : '';
        if (! str_starts_with($header, '%PDF')) {
            return 'This file is not a valid PDF. Upload the official GST or Udyam certificate.';
        }

        // Block obviously huge / embedded-script style abuse: very small text PDFs are ok;
        // reject if client name suggests zip/exe masquerading.
        $original = strtolower((string) $file->getClientOriginalName());
        foreach (['.exe', '.zip', '.js', '.html', '.php', '.scr'] as $bad) {
            if (str_contains($original, $bad)) {
                return 'This file name is not allowed. Upload a .pdf GST or Udyam certificate only.';
            }
        }

        return null;
    }

    private function ocrToolsAvailable(): bool
    {
        foreach ([
            ['pdftoppm', '/opt/homebrew/bin/pdftoppm', '/usr/local/bin/pdftoppm', '/usr/bin/pdftoppm'],
            ['tesseract', '/opt/homebrew/bin/tesseract', '/usr/local/bin/tesseract', '/usr/bin/tesseract'],
        ] as $candidates) {
            $found = false;
            foreach ($candidates as $bin) {
                if (str_contains($bin, '/')) {
                    if (is_executable($bin)) {
                        $found = true;
                        break;
                    }

                    continue;
                }
                $which = trim((string) shell_exec('command -v '.escapeshellarg($bin).' 2>/dev/null'));
                if ($which !== '' && is_executable($which)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }
}
