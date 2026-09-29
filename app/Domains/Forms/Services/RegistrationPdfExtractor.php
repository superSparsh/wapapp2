<?php

declare(strict_types=1);

namespace App\Domains\Forms\Services;

/**
 * Best-effort GST / Udyam PDF field extraction (legacy HomeController parity).
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
            'pan' => null,
            'document_type' => null,
        ];

        if ($absolutePath === null || $absolutePath === '' || ! is_file($absolutePath)) {
            return $result;
        }

        $text = $this->extractText($absolutePath);
        if ($text === '') {
            return $result;
        }

        $normalized = (string) preg_replace('/[ \t]+/', ' ', $text);
        $upper = strtoupper($normalized);

        if (str_contains($upper, 'GOODS AND SERVICES TAX') || str_contains($upper, 'GSTIN')) {
            $result['document_type'] = 'GST';
        } elseif (str_contains($upper, 'UDYAM') || str_contains($upper, 'MSME')) {
            $result['document_type'] = 'UDYAM';
        }

        if (preg_match('/\b\d{2}[A-Z]{5}\d{4}[A-Z][A-Z0-9]Z[A-Z0-9]\b/i', $normalized, $m) === 1) {
            $result['gstin'] = strtoupper($m[0]);
        }

        if (preg_match('/\b[A-Z]{5}[0-9]{4}[A-Z]\b/i', $normalized, $m) === 1) {
            $result['pan'] = strtoupper($m[0]);
        }

        if (preg_match('/\b\d{6}\b/', $normalized, $m) === 1) {
            $result['pincode'] = $m[0];
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $cleanLines = [];
        foreach ($lines as $ln) {
            $ln = trim((string) $ln);
            if ($ln !== '') {
                $cleanLines[] = (string) preg_replace('/\s+/', ' ', $ln);
            }
        }

        $legal = $this->valueByLabels($cleanLines, ['Legal Name', 'Name of Business']);
        $trade = $this->valueByLabels($cleanLines, ['Trade Name']);
        $result['business_name'] = $legal
            ?: $trade
            ?: $this->valueByLabels($cleanLines, ['Enterprise Name', 'Name of Enterprise', 'Business Name']);
        $result['city'] = $this->valueByLabels($cleanLines, ['City', 'Town', 'Locality']);
        $result['state'] = $this->valueByLabels($cleanLines, ['State']);

        foreach ($cleanLines as $idx => $line) {
            if (preg_match('/\b(Address|Principal Place|Address of Business|Communication Address|Office Address)\b\s*[:\-]?\s*(.*)$/i', $line, $m) !== 1) {
                continue;
            }

            $chunks = [];
            $first = trim((string) ($m[2] ?? ''));
            if ($first !== '') {
                $chunks[] = $first;
            }
            for ($j = 1; $j <= 7; $j++) {
                $nextLine = trim((string) ($cleanLines[$idx + $j] ?? ''));
                if (
                    $nextLine === ''
                    || preg_match('/\b(GSTIN|PAN|Legal Name|Trade Name|Date of Registration|Constitution|District|State|Mobile|Email|Status)\b/i', $nextLine) === 1
                ) {
                    break;
                }
                $chunks[] = $nextLine;
            }

            $address = trim(implode(', ', array_filter($chunks)));
            if ($address !== '') {
                $result['business_address'] = $address;
                $parts = array_values(array_filter(array_map('trim', explode(',', $address))));
                $result['address_line_1'] = $parts[0] ?? null;
                if (count($parts) > 2) {
                    $result['address_line_2'] = implode(', ', array_slice($parts, 1, -2));
                }
                break;
            }
        }

        return $result;
    }

    private function extractText(string $absolutePath): string
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
