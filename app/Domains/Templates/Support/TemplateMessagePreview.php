<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use App\Models\Template;

/**
 * Legacy-aligned message bubble preview for Authentication and LTO templates.
 */
final class TemplateMessagePreview
{
    public static function isAuthentication(Template $template, array $payload): bool
    {
        $auth = is_array($payload['auth'] ?? null) ? $payload['auth'] : [];

        return TemplateCategoryCatalog::isAuthentication((string) $template->category)
            || (bool) ($auth['enabled'] ?? false);
    }

    public static function isLto(Template $template, array $payload): bool
    {
        $lto = is_array($payload['lto'] ?? null) ? $payload['lto'] : [];

        return TemplateCategoryCatalog::isLto((string) $template->category)
            || (bool) ($lto['enabled'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $buttons
     */
    public static function applyAuthentication(
        array $payload,
        string &$rawBody,
        string &$footerText,
        array &$buttons,
        string &$headerType,
    ): void {
        $auth = is_array($payload['auth'] ?? null) ? $payload['auth'] : [];

        $headerType = 'none';
        $buttons = [];

        if (trim($rawBody) === '') {
            $rawBody = '$(verificationCode) is your verification code.';
        }

        if ($auth['add_secret_recommendation'] ?? false) {
            $base = rtrim($rawBody, '. ');
            $rawBody = $base.'. For your security, do not share this code.';
        }

        if ($auth['expiration_time'] ?? false) {
            $minutes = (int) ($auth['expiration_minutes'] ?? 10);
            $footerText = "This code expires in {$minutes} minutes.";
        } else {
            $footerText = '';
        }

        $buttons[] = [
            'text' => self::authenticationButtonLabel($auth),
            'type' => 'copy_code',
            'url' => '',
            'flow_id' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    public static function authenticationButtonLabel(array $auth): string
    {
        $autoFill = (bool) ($auth['auto_fill'] ?? false);
        $zeroTap = (bool) ($auth['zero_tap'] ?? false);

        // Legacy: autofill + not zero-tap → Autofill label; otherwise fixed "Copy code".
        if ($autoFill && ! $zeroTap) {
            return (string) ($auth['fill_button_text'] ?? 'Autofill');
        }

        return 'Copy code';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $buttons
     * @return array<string, mixed>|null
     */
    public static function ltoOfferFromPayload(array $payload, array $buttons): ?array
    {
        $lto = is_array($payload['lto'] ?? null) ? $payload['lto'] : [];
        if (! ($lto['enabled'] ?? false) && trim((string) ($lto['discount_introduction'] ?? '')) === '') {
            return null;
        }

        $coupon = trim((string) ($lto['coupon_code'] ?? ''));
        if ($coupon === '') {
            foreach ($buttons as $button) {
                if (is_array($button) && ($button['type'] ?? '') === 'copy_code') {
                    $coupon = trim((string) ($button['url'] ?? ''));
                    break;
                }
            }
        }

        $intro = trim((string) ($lto['discount_introduction'] ?? ''));
        if ($intro === '') {
            $intro = 'Get 20% off';
        }

        return [
            'introduction' => $intro,
            'coupon_code' => $coupon !== '' ? $coupon : '$(offer_code)',
            'show_expiration' => (bool) ($lto['expiration_time'] ?? false),
            'expiration_label' => '$(offerExpirationTimeMs) End',
        ];
    }
}
