<?php

declare(strict_types=1);

namespace App\Domains\Forms\Services;

/**
 * GST / Udyam registration PDF field extraction.
 *
 * Supports:
 * 1) Official Form GST REG-06 text-layer PDFs (FlateDecode + Tj operators)
 * 2) "Microsoft Print to PDF" / vector-outline certificates via OCR fallback
 *    (pdftoppm + tesseract), with light image cleanup for pale grey glyphs
 */
class RegistrationPdfExtractor
{
    /**
     * @return array{
     *     business_name: ?string,
     *     business_address: ?string,
     *     address_line_1: ?string,
     *     address_line_2: ?string,
     *     city: ?string,
     *     state: ?string,
     *     pincode: ?string,
     *     gstin: ?string,
     *     udyam: ?string,
     *     registration_number: ?string,
     *     pan: ?string,
     *     document_type: ?string
     * }
     */
    public function extract(?string $absolutePath): array
    {
        $empty = [
            'business_name' => null,
            'business_address' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'state' => null,
            'pincode' => null,
            'gstin' => null,
            'udyam' => null,
            'registration_number' => null,
            'pan' => null,
            'document_type' => null,
        ];

        if ($absolutePath === null || $absolutePath === '' || ! is_file($absolutePath)) {
            return $empty;
        }

        $tokens = $this->extractPdfTextTokens($absolutePath);
        $text = trim(implode("\n", $tokens));
        if ($text === '') {
            $text = $this->extractTextFallback($absolutePath);
            $tokens = $this->linesFromText($text);
        }

        $result = $this->parseFieldsFromText($text, $tokens);

        // Print-to-PDF / scanned certificates often have no extractable text layer.
        if (! $this->hasUsableRegistration($result)) {
            $ocrText = $this->extractTextViaOcr($absolutePath);
            if ($ocrText !== '') {
                $ocrTokens = $this->linesFromText($ocrText);
                $ocrResult = $this->parseFieldsFromText($ocrText, $ocrTokens);
                $result = $this->mergeExtractionResults($result, $ocrResult);
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $tokens
     * @return array{
     *     business_name: ?string,
     *     business_address: ?string,
     *     address_line_1: ?string,
     *     address_line_2: ?string,
     *     city: ?string,
     *     state: ?string,
     *     pincode: ?string,
     *     gstin: ?string,
     *     udyam: ?string,
     *     registration_number: ?string,
     *     pan: ?string,
     *     document_type: ?string
     * }
     */
    private function parseFieldsFromText(string $text, array $tokens): array
    {
        $result = [
            'business_name' => null,
            'business_address' => null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'state' => null,
            'pincode' => null,
            'gstin' => null,
            'udyam' => null,
            'registration_number' => null,
            'pan' => null,
            'document_type' => null,
        ];

        if ($text === '' && $tokens === []) {
            return $result;
        }

        $lines = $tokens !== [] ? $tokens : $this->linesFromText($text);
        $normalized = (string) preg_replace('/[ \t]+/', ' ', $text);
        $upper = strtoupper($normalized);

        // MSME first — "REGISTRATION CERTIFICATE" appears on both GST and Udyam/UAM PDFs.
        $looksUdyam = str_contains($upper, 'UDYAM REGISTRATION')
            || str_contains($upper, 'UDYAM REGISTRATION NUMBER')
            || str_contains($upper, 'UDYAM-')
            || str_contains($upper, 'UDYOG AADHAAR')
            || str_contains($upper, 'UAM NO')
            || str_contains($upper, 'UDYOGAADHAAR.GOV.IN')
            || str_contains($upper, 'UDYAMREGISTRATION.GOV.IN')
            || (str_contains($upper, 'MSME') && (str_contains($upper, 'UDYAM') || str_contains($upper, 'MINISTRY OF MICRO')));
        $looksGst = str_contains($upper, 'FORM GST REG-06')
            || str_contains($upper, 'GOODS AND SERVICES TAX')
            || str_contains($upper, 'GSTIN/UIN')
            || str_contains($upper, 'GSTIN / UIN')
            || (str_contains($upper, 'GSTIN') && str_contains($upper, 'LEGAL NAME'))
            || (str_contains($upper, 'REGISTRATION NUMBER') && str_contains($upper, 'LEGAL NAME') && ! $looksUdyam);

        if ($looksUdyam) {
            $result['document_type'] = 'UDYAM';
        } elseif ($looksGst) {
            $result['document_type'] = 'GST';
        }

        $gstinFromLabel = $this->valueAfterLabels($lines, [
            'Registration Number',
            'Registration Number :',
            'GSTIN',
            'GSTIN/UIN',
            'GSTIN / UIN',
        ]);
        // Only hunt GSTIN when this is (or might be) a GST certificate — avoids false hits on MSME PDFs.
        if (($result['document_type'] ?? null) !== 'UDYAM') {
            $result['gstin'] = $this->findGstinCandidate((string) ($gstinFromLabel ?? ''), $normalized);
        }

        $udyamFromLabel = $this->valueAfterLabels($lines, [
            'Udyam Registration Number',
            'UDYAM REGISTRATION NUMBER',
            'Udyam Registration No',
            'UDYAM Registration Number',
        ]) ?: $this->valueFromMessyLine($lines, [
            'Udyam Registration Number',
            'UDYAM REGISTRATION NUMBER',
            'Udyam Registration No',
        ]);
        if (is_string($udyamFromLabel) && preg_match('/\bUDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}\b/i', $udyamFromLabel, $m) === 1) {
            $result['udyam'] = strtoupper($m[0]);
        } elseif (preg_match('/\bUDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}\b/i', $normalized, $m) === 1) {
            $result['udyam'] = strtoupper($m[0]);
        }

        // Legacy MSME: Udyog Aadhaar / UAM (e.g. KR03D0042254)
        $uam = $this->findUamCandidate($lines, $normalized);
        if ($uam !== null && blank($result['udyam'])) {
            $result['udyam'] = $uam;
            if ($result['document_type'] === null) {
                $result['document_type'] = 'UDYAM';
            }
        }

        if (($result['document_type'] ?? null) === 'UDYAM' && filled($result['udyam'])) {
            $result['registration_number'] = $result['udyam'];
        } elseif (filled($result['gstin'])) {
            $result['registration_number'] = $result['gstin'];
            if ($result['document_type'] === null) {
                $result['document_type'] = 'GST';
            }
        } elseif (filled($result['udyam'])) {
            $result['registration_number'] = $result['udyam'];
            if ($result['document_type'] === null) {
                $result['document_type'] = 'UDYAM';
            }
        }

        if (filled($result['gstin']) && preg_match('/^[0-9]{2}([A-Z]{5}[0-9]{4}[A-Z])/', (string) $result['gstin'], $m) === 1) {
            $result['pan'] = $m[1];
        } elseif (($result['document_type'] ?? null) === 'GST' && preg_match('/\b[A-Z]{5}[0-9]{4}[A-Z]\b/i', $normalized, $m) === 1) {
            $result['pan'] = strtoupper($m[0]);
        }

        $enterpriseLabels = [
            'NAME OF ENTERPRISE',
            'Name of Enterprise',
            'Name of the Enterprise',
            'Legal Name',
            'Name of Business',
            'Enterprise Name',
        ];
        $legal = $this->valueAfterLabels($lines, $enterpriseLabels)
            ?: $this->valueByLabels($lines, $enterpriseLabels)
            ?: $this->valueFromMessyLine($lines, $enterpriseLabels);
        $trade = $this->valueAfterLabels($lines, ['Trade Name, if any', 'Trade Name', 'Business Name', 'Additional trade names, if any'])
            ?: $this->valueByLabels($lines, ['Trade Name, if any', 'Trade Name', 'Business Name']);

        // OCR / print layouts often glue "Legal Name MEYDAN INFRA LLP" on one line.
        if ($trade === null) {
            $trade = $this->valueFromMessyLine($lines, ['Trade Name, if any', 'Trade Name']);
        }

        $result['business_name'] = $this->cleanName($legal)
            ?: $this->cleanName($trade);

        $address = $this->extractPrincipalAddress($lines, $text);
        if ($address === null) {
            $address = $this->extractStructuredAddressFromLines($lines);
        }
        if ($address !== null) {
            $result['business_address'] = $address;
            $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
            $result['address_line_1'] = $parts[0] ?? null;
            if (count($parts) > 2) {
                $result['address_line_2'] = implode(', ', array_slice($parts, 1, -2));
            }
            if (preg_match('/\b(\d{6})\b/', $address, $m) === 1) {
                $result['pincode'] = $m[1];
            }
            if (preg_match('/,\s*([A-Za-z ]+),\s*\d{6}\s*$/', $address, $m) === 1) {
                $result['state'] = trim($m[1]);
            }
        }

        $pinFromLabel = $this->valueFromMessyLine($lines, ['PIN Code', 'Pincode', 'Pin Code']);
        if (is_string($pinFromLabel) && preg_match('/\b(\d{6})\b/', $pinFromLabel, $m) === 1) {
            $result['pincode'] = $m[1];
        }

        // Only guess loose pincode once we know this is a registration certificate.
        if ($result['pincode'] === null && $result['document_type'] !== null && preg_match('/\b(\d{6})\b/', $normalized, $m) === 1) {
            $result['pincode'] = $m[1];
        }

        $result['city'] = $result['city']
            ?: $this->valueAfterLabels($lines, ['City/Town/Village', 'City', 'Town', 'Locality'])
            ?: $this->valueFromMessyLine($lines, ['City/Town/Village', 'City', 'Town', 'Locality']);
        $result['state'] = $result['state']
            ?: $this->valueAfterLabels($lines, ['State', 'Resident of State'])
            ?: $this->valueFromMessyLine($lines, ['State']);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function hasUsableRegistration(array $result): bool
    {
        return filled($result['registration_number'] ?? null) && filled($result['business_name'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  array<string, mixed>  $secondary
     * @return array<string, mixed>
     */
    private function mergeExtractionResults(array $primary, array $secondary): array
    {
        foreach ($secondary as $key => $value) {
            if (! filled($primary[$key] ?? null) && filled($value)) {
                $primary[$key] = $value;
            }
        }

        return $primary;
    }

    /**
     * @param  list<string>  $lines
     */
    private function findUamCandidate(array $lines, string $haystack): ?string
    {
        $labeled = $this->valueAfterLabels($lines, [
            'Udyog Aadhaar Number',
            'Udyog Aadhaar No',
            'UAM No',
            'UAM No.',
            'UAM Number',
        ]) ?: $this->valueFromMessyLine($lines, [
            'Udyog Aadhaar Number',
            'Udyog Aadhaar No',
            'UAM No',
            'UAM No.',
            'UAM Number',
        ]);

        foreach ([$labeled, $haystack] as $source) {
            if (! is_string($source) || $source === '') {
                continue;
            }
            if (preg_match('/\b([A-Z]{2}[0-9]{2}[A-Z][0-9]{7})\b/i', $source, $m) === 1) {
                return strtoupper($m[1]);
            }
        }

        return null;
    }

    private function findGstinCandidate(string $labeled, string $haystack): ?string
    {
        // Prefer value next to Registration Number / GSTIN labels (OCR print layouts).
        if (preg_match('/Registration\s*Number\s*:?\s*([0-9A-Za-z]{14,18})/i', $haystack, $m) === 1) {
            $normalized = $this->normalizeOcrGstin($m[1]);
            if ($normalized !== null) {
                return $normalized;
            }
        }
        if (preg_match('/GSTIN(?:\s*\/\s*UIN)?\s*:?\s*([0-9A-Za-z]{14,18})/i', $haystack, $m) === 1) {
            $normalized = $this->normalizeOcrGstin($m[1]);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        foreach ([$labeled, $haystack] as $source) {
            $source = strtoupper((string) preg_replace('/\s+/', '', $source));
            if ($source === '') {
                continue;
            }
            // Strict match first.
            if (preg_match('/\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]/', $source, $m) === 1) {
                return $m[0];
            }
            // OCR-tolerant 15–16 char blobs near GSTIN shape.
            if (preg_match_all('/[0-9A-Z]{14,16}/', $source, $matches) > 0) {
                foreach ($matches[0] as $blob) {
                    $normalized = $this->normalizeOcrGstin($blob);
                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeOcrGstin(string $raw): ?string
    {
        $s = strtoupper(preg_replace('/[^0-9A-Z]/', '', $raw) ?? '');
        if ($s === '') {
            return null;
        }

        // Drop common OCR insertions in state-code area:
        // 0I3 / OI3 / 0L3 / 0P3 / OP3 -> 03 (extra letter between state digits)
        if (strlen($s) === 16 && preg_match('/^[0O][A-ZILP]([0-9].+)$/', $s, $m) === 1) {
            $s = '0'.$m[1];
        }
        // 03xABYFM... where x is junk letter after valid 2-digit state
        if (strlen($s) === 16 && preg_match('/^([0-9O]{2})[A-Z]([A-Z]{5}[0-9A-Z]{7})$/', $s, $m) === 1) {
            $state = str_replace(['O', 'I', 'L'], ['0', '1', '1'], $m[1]);
            if (preg_match('/^\d{2}$/', $state) === 1) {
                $s = $state.$m[2];
            }
        }
        // Leading O mistaken for 0 on an otherwise 15-char GSTIN (O3ABYFM...)
        if (strlen($s) === 15 && preg_match('/^O(\d[A-Z0-9]{13})$/', $s, $m) === 1) {
            $s = '0'.$m[1];
        }

        // Build 15-char candidate with positional O/0 and I/1 fixes.
        if (strlen($s) !== 15) {
            return null;
        }

        $chars = str_split($s);
        // positions 0-1 state digits
        foreach ([0, 1] as $i) {
            $chars[$i] = match ($chars[$i]) {
                'O' => '0',
                'I', 'L' => '1',
                default => $chars[$i],
            };
        }
        // positions 2-6 letters (PAN first 5) — digits mistaken as letters rare; 0->O, 1->I
        foreach ([2, 3, 4, 5, 6] as $i) {
            $chars[$i] = match ($chars[$i]) {
                '0' => 'O',
                '1' => 'I',
                default => $chars[$i],
            };
        }
        // positions 7-10 digits
        foreach ([7, 8, 9, 10] as $i) {
            $chars[$i] = match ($chars[$i]) {
                'O' => '0',
                'I', 'L' => '1',
                default => $chars[$i],
            };
        }
        // position 11 letter
        $chars[11] = match ($chars[11]) {
            '0' => 'O',
            '1' => 'I',
            default => $chars[11],
        };
        // position 13 must be Z for GSTIN
        if ($chars[13] === '2') {
            $chars[13] = 'Z';
        }

        $candidate = implode('', $chars);
        if (preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $candidate) === 1) {
            return $candidate;
        }

        return null;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $labels
     */
    private function valueFromMessyLine(array $lines, array $labels): ?string
    {
        foreach ($lines as $line) {
            $clean = trim((string) preg_replace('/^[|\d\.\[\]\s]+/', '', $line));
            foreach ($labels as $label) {
                if (preg_match('/'.preg_quote($label, '/').'\s*[:\-\|]?\s*(.+)$/i', $clean, $m) === 1) {
                    $value = trim((string) ($m[1] ?? ''));
                    $value = trim($value, " \t|:[]");
                    if ($value !== '' && ! $this->looksLikeLabel($value)) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Newer REG-06 print layouts break address into labeled rows.
     *
     * @param  list<string>  $lines
     */
    private function extractStructuredAddressFromLines(array $lines): ?string
    {
        $parts = [];
        $keys = [
            'Floor No',
            'Building No./Flat No',
            'Building No/Flat No',
            'Name Of Premises/Building',
            'Road/Street',
            'Nearby Landmark',
            'Locality/Sub Locality',
            'City/Town/Village',
            'District',
            'State',
            'PIN Code',
        ];
        foreach ($keys as $key) {
            $val = $this->valueFromMessyLine($lines, [$key]);
            if ($val !== null && $val !== '') {
                // Strip repeated key prefixes like "UPPER..." only.
                $parts[] = $val;
            }
        }

        $parts = array_values(array_unique(array_filter(array_map(
            static fn (string $p): string => trim($p),
            $parts
        ))));

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Rasterize PDF pages and OCR (for Microsoft Print-to-PDF / scanned certificates).
     */
    private function extractTextViaOcr(string $absolutePath): string
    {
        $pdftoppm = $this->findBinary(['pdftoppm', '/opt/homebrew/bin/pdftoppm', '/usr/local/bin/pdftoppm', '/usr/bin/pdftoppm']);
        $tesseract = $this->findBinary(['tesseract', '/opt/homebrew/bin/tesseract', '/usr/local/bin/tesseract', '/usr/bin/tesseract']);
        if ($pdftoppm === null || $tesseract === null) {
            return '';
        }

        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'wapapp-gst-ocr-'.bin2hex(random_bytes(6));
        if (! @mkdir($tmpDir, 0700, true) && ! is_dir($tmpDir)) {
            return '';
        }

        try {
            $prefix = $tmpDir.DIRECTORY_SEPARATOR.'page';
            $cmd = escapeshellarg($pdftoppm)
                .' -png -r 200 -f 1 -l 2 '
                .escapeshellarg($absolutePath).' '.escapeshellarg($prefix).' 2>/dev/null';
            @shell_exec($cmd);

            $images = glob($prefix.'-*.png') ?: [];
            if ($images === []) {
                return '';
            }

            $chunks = [];
            foreach ($images as $image) {
                $prepared = $this->prepareImageForOcr($image);
                // Prefer high-contrast pass (pale grey Print-to-PDF glyphs), then raw.
                foreach (array_values(array_filter([$prepared, $image])) as $target) {
                    foreach ([6, 4] as $psm) {
                        $ocrCmd = escapeshellarg($tesseract).' '
                            .escapeshellarg($target)
                            .' stdout -l eng --psm '.$psm.' 2>/dev/null';
                        $out = trim((string) shell_exec($ocrCmd));
                        if ($out !== '') {
                            $chunks[] = $out;
                            // One good pass is enough for this page.
                            break 2;
                        }
                    }
                }
                if ($prepared !== null && is_file($prepared)) {
                    @unlink($prepared);
                }
                @unlink($image);
            }

            return trim(implode("\n", $chunks));
        } finally {
            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }

    private function prepareImageForOcr(string $imagePath): ?string
    {
        if (! function_exists('imagecreatefrompng')) {
            return null;
        }

        $src = @imagecreatefrompng($imagePath);
        if ($src === false) {
            return null;
        }

        $width = imagesx($src);
        $height = imagesy($src);
        $dst = imagecreatetruecolor($width, $height);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        // Convert pale grey vector printouts to high-contrast black text on white.
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($src, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = (int) (($r + $g + $b) / 3);
                $val = $gray < 200 ? 0 : 255;
                $color = imagecolorallocate($dst, $val, $val, $val);
                if ($color !== false) {
                    imagesetpixel($dst, $x, $y, $color);
                }
            }
        }

        $out = $imagePath.'.bw.png';
        $ok = imagepng($dst, $out);
        imagedestroy($src);
        imagedestroy($dst);

        return $ok ? $out : null;
    }

    /**
     * @param  list<string>  $candidates
     */
    private function findBinary(array $candidates): ?string
    {
        foreach ($candidates as $bin) {
            if (str_contains($bin, '/')) {
                if (is_executable($bin)) {
                    return $bin;
                }

                continue;
            }
            $found = trim((string) shell_exec('command -v '.escapeshellarg($bin).' 2>/dev/null'));
            if ($found !== '' && is_executable($found)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Pull ordered text tokens from PDF content (FlateDecode streams + Tj/TJ).
     *
     * @return list<string>
     */
    private function extractPdfTextTokens(string $absolutePath): array
    {
        $raw = @file_get_contents($absolutePath);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $tokens = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $data = $match[1] ?? '';
            if ($data === '') {
                continue;
            }
            // Skip obvious JPEG/PNG image payloads.
            if (str_starts_with($data, "\xFF\xD8") || str_starts_with($data, "\x89PNG")) {
                continue;
            }

            $decoded = $this->inflatePdfStream($data);
            if ($decoded === null || $decoded === '') {
                // Some content streams are uncompressed.
                $decoded = $data;
            }

            foreach ($this->tokensFromContentStream($decoded) as $token) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private function inflatePdfStream(string $data): ?string
    {
        $out = @gzuncompress($data);
        if (is_string($out) && $out !== '') {
            return $out;
        }

        $out = @gzinflate($data);
        if (is_string($out) && $out !== '') {
            return $out;
        }

        // Raw deflate (PDF FlateDecode often omits zlib wrapper).
        if (function_exists('inflate_init') && function_exists('inflate_add')) {
            try {
                $ctx = inflate_init(ZLIB_ENCODING_RAW);
                if ($ctx !== false) {
                    $raw = inflate_add($ctx, $data, ZLIB_FINISH);
                    if (is_string($raw) && $raw !== '') {
                        return $raw;
                    }
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $out = @zlib_decode($data);
        if (is_string($out) && $out !== '') {
            return $out;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function tokensFromContentStream(string $content): array
    {
        $tokens = [];

        // Literal strings: (Hello \(world\)) Tj  or  (...)'
        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*(?:Tj|\'|")/s', $content, $m) > 0) {
            foreach ($m[0] as $op) {
                if (preg_match('/^\((.*)\)\s*(?:Tj|\'|")$/s', $op, $inner) !== 1) {
                    continue;
                }
                $token = $this->unescapePdfString($inner[1]);
                $token = trim((string) preg_replace('/\s+/', ' ', $token));
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }

        // Array show text: [(Hello) -10 (World)] TJ
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $content, $arrays) > 0) {
            foreach ($arrays[1] as $arrayBody) {
                $parts = [];
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/', $arrayBody, $strs) > 0) {
                    foreach ($strs[0] as $str) {
                        $parts[] = $this->unescapePdfString(substr($str, 1, -1));
                    }
                }
                $joined = trim((string) preg_replace('/\s+/', ' ', implode('', $parts)));
                if ($joined !== '') {
                    $tokens[] = $joined;
                }
            }
        }

        return $tokens;
    }

    private function unescapePdfString(string $value): string
    {
        $value = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'], ['(', ')', '\\', "\n", "\r", "\t"], $value);

        return $value;
    }

    private function extractTextFallback(string $absolutePath): string
    {
        $pdftotext = $this->findBinary([
            'pdftotext',
            '/opt/homebrew/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/usr/bin/pdftotext',
        ]);
        if ($pdftotext !== null) {
            $cmd = escapeshellarg($pdftotext).' -layout '.escapeshellarg($absolutePath).' - 2>/dev/null';
            $out = (string) shell_exec($cmd);
            if (trim($out) !== '') {
                return $out;
            }
        }

        // Do not scrape binary PDF bytes — that invents false "text" and can
        // short-circuit the OCR path for Microsoft Print-to-PDF certificates.
        return '';
    }

    /**
     * REG-06 often puts labels and values on consecutive Tj tokens, sometimes
     * splitting "Address of Principal Place of" + "Business" then address lines.
     *
     * @param  list<string>  $tokens
     * @param  list<string>  $labels
     */
    private function valueAfterLabels(array $tokens, array $labels): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $current = trim($tokens[$i]);
            $currentNorm = $this->normalizeLabel($current);

            foreach ($labels as $label) {
                $labelNorm = $this->normalizeLabel($label);
                if ($currentNorm === $labelNorm || str_starts_with($currentNorm, $labelNorm)) {
                    // Same-line remainder: "Legal Name MEYDAN INFRA LLP" / "Registration Number : 03..."
                    if (preg_match('/^'.preg_quote($label, '/').'\s*[:\-\|]?\s*(.+)$/i', $current, $same) === 1) {
                        $after = trim((string) ($same[1] ?? ''), " \t|:[]");
                        if ($after !== '' && ! $this->looksLikeLabel($after)) {
                            return $after;
                        }
                    }

                    for ($j = $i + 1; $j < min($i + 4, $count); $j++) {
                        $candidate = trim($tokens[$j]);
                        if ($candidate === '' || $this->looksLikeLabel($candidate)) {
                            continue;
                        }
                        // Skip orphaned word fragments that are still part of a split label.
                        if ($this->normalizeLabel($candidate) === 'business' && str_contains($labelNorm, 'address')) {
                            continue;
                        }

                        return $candidate;
                    }
                }

                // Split label across two tokens, e.g. "Address of Principal Place of" + "Business"
                if ($i + 1 < $count) {
                    $combined = $this->normalizeLabel($current.' '.$tokens[$i + 1]);
                    if ($combined === $labelNorm || str_starts_with($combined, $labelNorm)) {
                        for ($j = $i + 2; $j < min($i + 6, $count); $j++) {
                            $candidate = trim($tokens[$j]);
                            if ($candidate === '' || $this->looksLikeLabel($candidate)) {
                                continue;
                            }

                            return $candidate;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function extractPrincipalAddress(array $tokens, string $fullText): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $combined = $this->normalizeLabel($tokens[$i]);
            $next = $this->normalizeLabel($tokens[$i + 1] ?? '');
            $isAddressLabel = str_contains($combined, 'address of principal place')
                || str_contains($combined, 'principal place of business')
                || ($combined === 'address of principal place of' && $next === 'business')
                || $combined === 'address of business'
                || $combined === 'communication address'
                || $combined === 'office address';

            if (! $isAddressLabel) {
                continue;
            }

            $start = str_contains($combined, 'address of principal place of') && $next === 'business'
                ? $i + 2
                : $i + 1;

            $chunks = [];
            for ($j = $start; $j < min($start + 6, $count); $j++) {
                $line = trim($tokens[$j]);
                if ($line === '') {
                    continue;
                }
                if ($this->looksLikeLabel($line) && ! preg_match('/,\s*\d{6}\s*$/', $line)) {
                    // Keep address-looking lines even if they contain "State"-like words mid-address.
                    if (! preg_match('/\d/', $line) && ! str_contains($line, ',')) {
                        break;
                    }
                }
                if (preg_match('/^(Date of Liability|Period of Validity|Type of Registration|Particulars of Approving|Signature|Name|Designation|Jurisdictional Office|Constitution of Business)$/i', $line) === 1) {
                    break;
                }
                $chunks[] = $line;
                if (preg_match('/\b\d{6}\b/', $line) === 1) {
                    break;
                }
            }

            $address = trim(implode(', ', $chunks));
            $address = (string) preg_replace('/\s+,/', ',', $address);
            $address = (string) preg_replace('/,\s*,+/', ', ', $address);
            if ($address !== '') {
                return $address;
            }
        }

        // Fallback: line-oriented parse for text-layer PDFs.
        $lines = $this->linesFromText($fullText);
        foreach ($lines as $idx => $line) {
            if (preg_match('/\b(Address|Principal Place|Address of Business|Communication Address|Office Address)\b\s*[:\-]?\s*(.*)$/i', $line, $m) !== 1) {
                continue;
            }
            $chunks = [];
            $first = trim((string) ($m[2] ?? ''));
            if ($first !== '') {
                $chunks[] = $first;
            }
            for ($j = 1; $j <= 7; $j++) {
                $nextLine = trim((string) ($lines[$idx + $j] ?? ''));
                if (
                    $nextLine === ''
                    || preg_match('/\b(GSTIN|PAN|Legal Name|Trade Name|Date of Registration|Constitution|Date of Liability|Period of Validity|Type of Registration|Mobile|Email|Status)\b/i', $nextLine) === 1
                ) {
                    break;
                }
                $chunks[] = $nextLine;
            }
            $address = trim(implode(', ', array_filter($chunks)));
            if ($address !== '') {
                return $address;
            }
        }

        return null;
    }

    private function normalizeLabel(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/\s+/', ' ', $value);
        $value = rtrim($value, " :-\t");

        return $value;
    }

    private function looksLikeLabel(string $value): bool
    {
        $norm = $this->normalizeLabel($value);
        $labels = [
            'legal name',
            'name of enterprise',
            'name of the enterprise',
            'trade name',
            'trade name, if any',
            'constitution of business',
            'address of principal place of',
            'business',
            'date of liability',
            'period of validity',
            'type of registration',
            'type of enterprise',
            'registration number',
            'udyam registration number',
            'udyog aadhaar number',
            'uam no',
            'gstin',
            'from',
            'to',
            'signature',
            'name',
            'designation',
            'jurisdictional office',
            'particulars of approving authority',
            'annexure a',
            'annexure b',
        ];

        foreach ($labels as $label) {
            if ($norm === $label || str_starts_with($norm, $label)) {
                return true;
            }
        }

        return false;
    }

    private function cleanName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        $name = (string) preg_replace('/\s*%%EOF.*/i', '', $name);
        $name = (string) preg_replace('/\s*\b(endobj|endstream|trailer|xref)\b.*/i', '', $name);
        // OCR row junk: "| 2. [Trade Name, if any MEYDAN..." → keep trailing name if present
        if (preg_match('/Trade Name(?:, if any)?\s*[:\-\|]?\s*(.+)$/i', $name, $m) === 1) {
            $name = $m[1];
        }
        $name = (string) preg_replace('/^[|\d\.\s\[\]\-]+/', '', $name);
        $name = trim($name, " \t\n\r\0\x0B:-|[]");
        if ($name === '' || $this->looksLikeLabel($name)) {
            return null;
        }
        // Reject if it still looks like a whole OCR table row.
        if (preg_match('/\b(Trade Name|Constitution|Address of Principal|Additional trade)\b/i', $name) === 1) {
            return null;
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    private function linesFromText(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $clean = [];
        foreach ($lines as $ln) {
            $ln = trim((string) $ln);
            if ($ln !== '') {
                $clean[] = (string) preg_replace('/\s+/', ' ', $ln);
            }
        }

        return $clean;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $labels
     */
    private function valueByLabels(array $lines, array $labels): ?string
    {
        foreach ($lines as $line) {
            foreach ($labels as $label) {
                if (preg_match('/^'.preg_quote($label, '/').'\s*[:\-]?\s*(.+)$/i', $line, $m) === 1) {
                    $value = trim((string) ($m[1] ?? ''));
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }
}
