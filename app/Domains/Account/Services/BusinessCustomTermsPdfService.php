<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\BusinessCustomTerm;
use App\Models\Tenant;

/**
 * Minimal multi-page PDF for business custom terms (no DomPDF dependency).
 */
class BusinessCustomTermsPdfService
{
    public function filename(BusinessCustomTerm $term, Tenant $tenant): string
    {
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower((string) ($tenant->company_name ?: $tenant->name ?: 'business'))) ?: 'business';

        return 'custom-terms-'.$slug.'-v'.$term->version.'.pdf';
    }

    public function render(BusinessCustomTerm $term, Tenant $tenant): string
    {
        $header = [
            (string) config('legal.product_name', 'WapApp').' — Custom Customer Terms',
            'Business: '.((string) ($tenant->company_name ?: $tenant->name ?: 'Business')),
            'Title: '.$term->title,
            'Status: '.($term->status?->label() ?? (string) $term->status).' · Version '.$term->version,
            'Generated: '.now('Asia/Kolkata')->format('d M Y H:i').' IST',
            'Note: Internal / draft documents remain login-gated until leadership publishes.',
            '',
        ];

        $bodyLines = $this->wrapText((string) $term->body, 92);
        $allLines = array_merge($header, $bodyLines === [] ? ['(No terms text.)'] : $bodyLines);

        $linesPerPage = 42;
        $pages = array_chunk($allLines, $linesPerPage);
        if ($pages === []) {
            $pages = [[ '(empty)' ]];
        }

        $pageStreams = [];
        foreach ($pages as $pageIndex => $pageLines) {
            $content = "BT /F1 10 Tf 40 760 Td\n";
            foreach ($pageLines as $i => $line) {
                $escaped = $this->escapePdfText($line);
                if ($i === 0) {
                    $content .= "({$escaped}) Tj\n";
                } else {
                    $content .= "0 -16 Td ({$escaped}) Tj\n";
                }
            }
            $footer = 'Page '.($pageIndex + 1).' of '.count($pages);
            $content .= "0 -24 Td (".$this->escapePdfText($footer).") Tj\n";
            $content .= "ET\n";
            $pageStreams[] = $content;
        }

        return $this->assemblePdf($pageStreams);
    }

    /**
     * @param  list<string>  $pageStreams
     */
    private function assemblePdf(array $pageStreams): string
    {
        $objects = [];
        $objects[] = '1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj';

        $pageCount = count($pageStreams);
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            // page object numbers: 3,5,7,... content: 4,6,8,... font shared last
            $kids[] = (3 + ($i * 2)).' 0 R';
        }
        $fontObjNum = 3 + ($pageCount * 2);
        $objects[] = '2 0 obj<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>endobj';

        foreach ($pageStreams as $i => $stream) {
            $pageObj = 3 + ($i * 2);
            $contentObj = $pageObj + 1;
            $objects[] = $pageObj.' 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.$contentObj.' 0 R /Resources << /Font << /F1 '.$fontObjNum.' 0 R >> >> >>endobj';
            $objects[] = $contentObj.' 0 obj<< /Length '.strlen($stream).' >>stream'."\n".$stream.'endstream endobj';
        }

        $objects[] = $fontObjNum.' 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj';

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

        return $pdf;
    }

    /**
     * @return list<string>
     */
    private function wrapText(string $text, int $width): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $out = [];
        foreach (explode("\n", $text) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $out[] = '';
                continue;
            }
            // Helvetica cannot encode all UTF-8; strip to Latin-1-ish printable.
            $paragraph = $this->toPdfSafe($paragraph);
            $wrapped = wordwrap($paragraph, $width, "\n", true);
            foreach (explode("\n", $wrapped) as $line) {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function toPdfSafe(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if (! is_string($converted) || $converted === '') {
            return preg_replace('/[^\x20-\x7E]/', '?', $text) ?: $text;
        }

        return $converted;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
