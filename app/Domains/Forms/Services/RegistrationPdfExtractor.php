<?php

declare(strict_types=1);

namespace App\Domains\Forms\Services;

/**
 * GST / Udyam registration PDF field extraction.
 *
 * Handles official Form GST REG-06 style certificates where text lives in
 * FlateDecode content streams as (... )Tj operators (not plain ASCII), plus
 * pdftotext / raw fallbacks for text-layer PDFs.
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

        if ($absolutePath === null || $absolutePath === '' || ! is_file($absolutePath)) {
            return $result;
        }

        $tokens = $this->extractPdfTextTokens($absolutePath);
        $text = trim(implode("\n", $tokens));
        if ($text === '') {
            $text = $this->extractTextFallback($absolutePath);
            $tokens = $this->linesFromText($text);
        }

        if ($text === '' && $tokens === []) {
            return $result;
        }

        $normalized = (string) preg_replace('/[ \t]+/', ' ', $text);
        $upper = strtoupper($normalized);

        $looksGst = str_contains($upper, 'FORM GST REG-06')
            || str_contains($upper, 'GOODS AND SERVICES TAX')
            || str_contains($upper, 'GSTIN/UIN')
            || str_contains($upper, 'GSTIN / UIN')
            || str_contains($upper, 'REGISTRATION CERTIFICATE')
            || (str_contains($upper, 'GSTIN') && str_contains($upper, 'LEGAL NAME'))
            || (str_contains($upper, 'REGISTRATION NUMBER') && str_contains($upper, 'LEGAL NAME'));
        $looksUdyam = str_contains($upper, 'UDYAM REGISTRATION')
            || str_contains($upper, 'UDYAM-')
            || (str_contains($upper, 'MSME') && (str_contains($upper, 'UDYAM') || str_contains($upper, 'MINISTRY OF MICRO')));

        if ($looksGst) {
            $result['document_type'] = 'GST';
        } elseif ($looksUdyam) {
            $result['document_type'] = 'UDYAM';
        }

        // Prefer labeled Registration Number (Form GST REG-06), then any GSTIN shape.
        $gstinFromLabel = $this->valueAfterLabels($tokens, [
            'Registration Number',
            'Registration Number :',
            'GSTIN',
            'GSTIN/UIN',
            'GSTIN / UIN',
        ]);
        if (is_string($gstinFromLabel) && preg_match('/\b\d{2}[A-Z]{5}\d{4}[A-Z][A-Z0-9]Z[A-Z0-9]\b/i', $gstinFromLabel, $m) === 1) {
            $result['gstin'] = strtoupper($m[0]);
        } elseif (preg_match('/\b\d{2}[A-Z]{5}\d{4}[A-Z][A-Z0-9]Z[A-Z0-9]\b/i', $normalized, $m) === 1) {
            $result['gstin'] = strtoupper($m[0]);
        }

        $udyamFromLabel = $this->valueAfterLabels($tokens, [
            'Udyam Registration Number',
            'Udyam Registration No',
            'UDYAM Registration Number',
        ]);
        if (is_string($udyamFromLabel) && preg_match('/\bUDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}\b/i', $udyamFromLabel, $m) === 1) {
            $result['udyam'] = strtoupper($m[0]);
        } elseif (preg_match('/\bUDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}\b/i', $normalized, $m) === 1) {
            $result['udyam'] = strtoupper($m[0]);
        }

        if (($result['document_type'] ?? null) === 'UDYAM' && filled($result['udyam'])) {
            $result['registration_number'] = $result['udyam'];
        } elseif (filled($result['gstin'])) {
            $result['registration_number'] = $result['gstin'];
        } elseif (filled($result['udyam'])) {
            $result['registration_number'] = $result['udyam'];
        }

        if (filled($result['gstin']) && preg_match('/^[0-9]{2}([A-Z]{5}[0-9]{4}[A-Z])/', (string) $result['gstin'], $m) === 1) {
            $result['pan'] = $m[1];
        } elseif (preg_match('/\b[A-Z]{5}[0-9]{4}[A-Z]\b/i', $normalized, $m) === 1) {
            $result['pan'] = strtoupper($m[0]);
        }

        $legal = $this->valueAfterLabels($tokens, ['Legal Name', 'Name of Business', 'Name of the Enterprise', 'Enterprise Name']);
        $trade = $this->valueAfterLabels($tokens, ['Trade Name, if any', 'Trade Name', 'Business Name']);
        $result['business_name'] = $this->cleanName($legal)
            ?: $this->cleanName($trade)
            ?: $this->cleanName($this->valueByLabels($this->linesFromText($text), [
                'Legal Name', 'Name of Business', 'Trade Name', 'Enterprise Name', 'Name of Enterprise', 'Business Name',
            ]));

        $address = $this->extractPrincipalAddress($tokens, $text);
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
            // Last non-pincode token before pin is often state on REG-06.
            if (preg_match('/,\s*([A-Za-z ]+),\s*\d{6}\s*$/', $address, $m) === 1) {
                $result['state'] = trim($m[1]);
            }
        }

        if ($result['pincode'] === null && preg_match('/\b\d{6}\b/', $normalized, $m) === 1) {
            $result['pincode'] = $m[0];
        }

        $result['city'] = $result['city']
            ?: $this->valueAfterLabels($tokens, ['City', 'Town', 'Locality']);
        $result['state'] = $result['state']
            ?: $this->valueAfterLabels($tokens, ['State', 'Resident of State']);

        return $result;
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
        $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($pdftotext !== '') {
            $cmd = escapeshellarg($pdftotext).' -layout '.escapeshellarg($absolutePath).' - 2>/dev/null';
            $out = (string) shell_exec($cmd);
            if (trim($out) !== '') {
                return $out;
            }
        }

        $raw = @file_get_contents($absolutePath);
        if (! is_string($raw) || $raw === '') {
            return '';
        }

        $raw = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $raw) ?? '';

        return trim((string) preg_replace('/\s{2,}/', ' ', $raw));
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
                    // Value may be on same token after ":" or on following token(s).
                    if (str_contains($current, ':')) {
                        $after = trim((string) substr($current, (int) strpos($current, ':') + 1));
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
            'trade name',
            'trade name, if any',
            'constitution of business',
            'address of principal place of',
            'business',
            'date of liability',
            'period of validity',
            'type of registration',
            'registration number',
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
        $name = trim($name, " \t\n\r\0\x0B:-");
        if ($name === '' || $this->looksLikeLabel($name)) {
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
