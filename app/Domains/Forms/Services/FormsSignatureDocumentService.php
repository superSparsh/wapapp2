<?php

declare(strict_types=1);

namespace App\Domains\Forms\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates a signature PNG + minimal signed PDF (legacy UserController::pdf parity, no DomPDF).
 */
class FormsSignatureDocumentService
{
    /**
     * @return array{signature_path: ?string, pdf_path: ?string, generated: bool}
     */
    public function generate(string $customerName, string $businessName, string $phoneDigits): array
    {
        try {
            $safeName = preg_replace('/[^a-zA-Z0-9 ]+/', '', $customerName) ?: $customerName;
            $dir = storage_path('app/public/forms/signatures');
            File::ensureDirectoryExists($dir);

            $signatureFileName = Str::slug($safeName !== '' ? $safeName : 'customer', '_').'_'.uniqid('', true).'.png';
            $signatureAbsPath = $dir.'/'.$signatureFileName;

            $this->writeSignaturePng($signatureAbsPath, (string) $safeName);

            $pdfFileName = Str::slug($safeName !== '' ? $safeName : 'customer', '_').'_'.uniqid('', true).'.pdf';
            $pdfAbsPath = $dir.'/'.$pdfFileName;
            $this->writeMinimalPdf(
                $pdfAbsPath,
                $businessName !== '' ? $businessName : 'Business',
                (string) $safeName,
                $phoneDigits,
            );

            return [
                'signature_path' => $signatureAbsPath,
                'pdf_path' => $pdfAbsPath,
                'generated' => is_file($pdfAbsPath),
            ];
        } catch (Throwable $e) {
            Log::warning('Forms onboarding signature/PDF generation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'signature_path' => null,
                'pdf_path' => null,
                'generated' => false,
            ];
        }
    }

    private function writeSignaturePng(string $path, string $safeName): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            File::put($path, '');

            return;
        }

        $im = imagecreatetruecolor(720, 240);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        $black = imagecolorallocate($im, 0, 0, 0);

        $text = mb_strlen($safeName) > 28 ? mb_substr($safeName, 0, 28).'...' : $safeName;
        $fontCandidates = [
            public_path('fonts/Sacramento-Regular.ttf'),
            public_path('fonts/Pacifico-Regular.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ];
        $fontFile = null;
        foreach ($fontCandidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                $fontFile = $candidate;
                break;
            }
        }

        if ($fontFile !== null && function_exists('imagettftext')) {
            imagettftext($im, 72, -8, 18, 120, $black, $fontFile, $text);
        } else {
            imagestring($im, 5, 20, 120, $text, $black);
        }

        imagepng($im, $path);
        imagedestroy($im);
    }

    private function writeMinimalPdf(string $path, string $businessName, string $signatory, string $phone): void
    {
        $lines = [
            'WhatsApp Automation Onboarding Agreement',
            '',
            'Business: '.$businessName,
            'Signatory: '.$signatory,
            'Phone: '.$phone,
            'Date: '.now('Asia/Kolkata')->format('d M Y H:i'),
            '',
            'This document confirms submission of onboarding details',
            'and acceptance of Meta WhatsApp Business terms.',
        ];

        $content = "BT /F1 12 Tf 50 750 Td\n";
        foreach ($lines as $i => $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            if ($i === 0) {
                $content .= "({$escaped}) Tj\n";
            } else {
                $content .= "0 -18 Td ({$escaped}) Tj\n";
            }
        }
        $content .= "ET\n";

        $objects = [];
        $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';
        $objects[] = '2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj';
        $objects[] = '3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>endobj';
        $objects[] = '4 0 obj<< /Length '.strlen($content).' >>stream'."\n".$content.'endstream endobj';
        $objects[] = '5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($offsets))."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= 'trailer<< /Size '.count($offsets).' /Root 1 0 R >>'."\n";
        $pdf .= 'startxref'."\n".$xrefPos."\n%%EOF";

        File::put($path, $pdf);
    }
}
