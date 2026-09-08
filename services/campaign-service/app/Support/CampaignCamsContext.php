<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Campaign;
use App\Support\PhoneNormalizer;

/**
 * CAMS send context stored on campaign.template_variables (no line/template tables in this service).
 */
final class CampaignCamsContext
{
    /**
     * @return array{
     *     template_code: string,
     *     language: string,
     *     line_phone: string,
     *     cust_space_id: string
     * }|null
     */
    public static function fromCampaign(Campaign $campaign): ?array
    {
        $vars = (array) ($campaign->template_variables ?? []);
        $code = trim((string) ($vars['template_code'] ?? ''));
        $phone = trim((string) ($vars['line_phone'] ?? $vars['from_phone'] ?? ''));
        $spaceId = trim((string) ($vars['cust_space_id'] ?? $vars['alibaba_cust_space_id'] ?? ''));

        if ($code === '' || $phone === '' || $spaceId === '') {
            return null;
        }

        $normalized = PhoneNormalizer::normalize($phone) ?? $phone;
        $from = str_starts_with($normalized, '+') ? $normalized : '+'.$normalized;

        return [
            'template_code' => $code,
            'language' => (string) ($vars['language'] ?? config('whatsapp.alibaba.default_language', 'en_GB')),
            'line_phone' => $from,
            'cust_space_id' => $spaceId,
        ];
    }

    /**
     * @param  array<string, mixed>  $campaignVars
     * @param  array<string, mixed>  $recipientVars
     * @return array<string, string>
     */
    public static function templateParams(array $campaignVars, array $recipientVars): array
    {
        $merged = array_merge($campaignVars, $recipientVars);
        $params = [];

        foreach ($merged as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }
            if (in_array((string) $key, [
                'template_code',
                'language',
                'line_phone',
                'from_phone',
                'cust_space_id',
                'alibaba_cust_space_id',
                'cams_group_id',
                'legacy_inbox_id',
            ], true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $params[(string) $key] = (string) ($value ?? '');
            }
        }

        return $params;
    }
}
