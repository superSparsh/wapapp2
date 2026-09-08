<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

/**
 * Alibaba CAMS template identity helpers.
 *
 * Language rules (legacy CampaignService::sendTemplate parity):
 * - blank / "en" / "english" → en_GB (config whatsapp.alibaba.default_language)
 * - anything else (en_US, hi, hi_IN, …) → kept as-is
 */
final class CamsTemplateIdentity
{
    public static function language(?string $language): string
    {
        $value = trim((string) $language);

        if ($value === '' || strcasecmp($value, 'en') === 0 || strcasecmp($value, 'english') === 0) {
            return (string) config('whatsapp.alibaba.default_language', 'en_GB');
        }

        return $value;
    }

    public static function code(?string $code, ?string $fallback = null): ?string
    {
        foreach ([$code, $fallback] as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '' || str_contains($value, ' ')) {
                continue;
            }
            // Importer fallback when legacy template_code was missing — not a real CAMS code.
            if (str_contains($value, '_legacy_')) {
                continue;
            }

            return $value;
        }

        return null;
    }
}
