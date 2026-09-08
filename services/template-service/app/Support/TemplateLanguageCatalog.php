<?php

declare(strict_types=1);

namespace App\Support;

final class TemplateLanguageCatalog
{
    /**
     * @return array<string, string> locale code => label
     */
    public static function options(): array
    {
        $labels = [
            'en_GB' => 'English (UK)',
            'en_US' => 'English (US)',
            'hi_IN' => 'Hindi',
            'af_ZA' => 'Afrikaans',
            'ar_AR' => 'Arabic',
            'az_AZ' => 'Azerbaijani',
            'bn_BD' => 'Bengali (Bangladesh)',
            'bn_IN' => 'Bengali (India)',
            'de_DE' => 'German',
            'es_AR' => 'Spanish (Argentina)',
            'es_ES' => 'Spanish (Spain)',
            'es_MX' => 'Spanish (Mexico)',
            'fr_FR' => 'French',
            'gu_IN' => 'Gujarati',
            'id_ID' => 'Indonesian',
            'it_IT' => 'Italian',
            'ja_JP' => 'Japanese',
            'kn_IN' => 'Kannada',
            'ko_KR' => 'Korean',
            'ml_IN' => 'Malayalam',
            'mr_IN' => 'Marathi',
            'ms_MY' => 'Malay',
            'nl_NL' => 'Dutch',
            'pa_IN' => 'Punjabi',
            'pl_PL' => 'Polish',
            'pt_BR' => 'Portuguese (Brazil)',
            'pt_PT' => 'Portuguese (Portugal)',
            'ru_RU' => 'Russian',
            'sk_SK' => 'Slovak',
            'ta_IN' => 'Tamil',
            'te_IN' => 'Telugu',
            'th_TH' => 'Thai',
            'tr_TR' => 'Turkish',
            'uk_UA' => 'Ukrainian',
            'ur_IN' => 'Urdu (India)',
            'ur_PK' => 'Urdu (Pakistan)',
            'vi_VN' => 'Vietnamese',
            'zh_CN' => 'Chinese (Simplified)',
            'zh_TW' => 'Chinese (Traditional)',
        ];

        $configured = config('templates.languages', []);

        if (! is_array($configured) || $configured === []) {
            return $labels;
        }

        $options = [];
        foreach ($configured as $code) {
            $options[$code] = $labels[$code] ?? str_replace('_', ' ', (string) $code);
        }

        return $options;
    }

    public static function label(?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        return self::options()[$code] ?? str_replace('_', ' ', $code);
    }
}
