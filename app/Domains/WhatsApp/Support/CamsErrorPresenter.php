<?php

declare(strict_types=1);

namespace App\Domains\WhatsApp\Support;

/**
 * Presents Alibaba CAMS / WhatsApp template errors for the UI.
 * Prefers the real provider / webhook Message; never invents vague filler copy.
 */
final class CamsErrorPresenter
{
    /**
     * Legacy-style last_status cleaner: keep the real CAMS/Meta Message/reason,
     * strip request ids / HTTP noise, never invent filler copy.
     */
    public static function cleanRejectionReason(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || self::isGenericFiller($raw)) {
            return '';
        }

        [$code, $message] = self::extractCodeAndMessage($raw);
        $text = $message !== '' ? $message : self::stripNoise($raw);
        $text = self::stripStoredFiller($text);

        // Legacy: remove Meta "(#123)" prefixes.
        $text = preg_replace('/\(#\d+\)\s*/', '', $text) ?? $text;
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        if ($text === '' || self::isGenericFiller($text)) {
            return $code !== '' && ! self::isGenericFiller($code) ? $code : '';
        }

        return \Illuminate\Support\Str::limit($text, 2000);
    }

    public static function isGenericFiller(?string $text): bool
    {
        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $text)));
        $normalized = rtrim($normalized, '.');

        return in_array($normalized, [
            'whatsapp rejected this template',
            'whatsapp rejected this template without a detailed reason',
            'no error details were returned by whatsapp',
            'no rejection details were returned by whatsapp',
            'something went wrong with the template, please contact the support',
        ], true);
    }

    /**
     * @return array{title: string, message: string, hint: string|null}
     */
    public static function present(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '' || self::isGenericFiller($raw)) {
            return [
                'title' => 'Submission failed',
                'message' => 'No error details were returned by WhatsApp.',
                'hint' => null,
            ];
        }

        [$code, $extractedMessage] = self::extractCodeAndMessage($raw);
        $cleaned = self::cleanRejectionReason($raw);
        $display = $cleaned !== ''
            ? $cleaned
            : self::polishProviderMessage($extractedMessage !== '' ? $extractedMessage : self::stripNoise($raw));

        if ($display === '' || self::isGenericFiller($display)) {
            return [
                'title' => 'Submission failed',
                'message' => 'No error details were returned by WhatsApp.',
                'hint' => null,
            ];
        }

        // Meta / webhook audit reasons are plain English — show them as-is (legacy last_status).
        if ($code === '' && self::looksLikeMetaAuditReason($display)) {
            return [
                'title' => self::titleForMetaReason($display),
                'message' => $display,
                'hint' => self::hintForMetaReason($display),
            ];
        }

        $codeKey = self::normalizeCode($code);
        $providerMessage = self::polishProviderMessage($extractedMessage !== '' ? $extractedMessage : $display);
        if ($providerMessage === '' || self::isGenericFiller($providerMessage)) {
            $providerMessage = $display;
        }
        $haystack = strtoupper($code.' '.$extractedMessage.' '.$display.' '.$raw);

        [$title, $hint, $fallbackMessage] = self::guidanceFor($codeKey, $haystack);

        $body = $providerMessage !== '' ? $providerMessage : ($fallbackMessage ?? '');
        if ($body === '' || self::isGenericFiller($body)) {
            $body = $display;
        }
        if ($body === '' || self::isGenericFiller($body)) {
            $body = $code !== '' ? $code : 'No error details were returned by WhatsApp.';
        }

        return [
            'title' => $title,
            'message' => $body,
            'hint' => $hint,
        ];
    }

    public static function friendlyMessage(?string $raw): string
    {
        $presented = self::present($raw);

        $parts = array_filter([
            $presented['message'],
            $presented['hint'],
        ], static fn (?string $part): bool => filled($part));

        return \Illuminate\Support\Str::limit(implode(' ', $parts), 1000);
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null} [title, hint, fallbackMessage]
     */
    private static function guidanceFor(string $codeKey, string $haystack): array
    {
        if (str_contains($haystack, 'FILEURLERROR') || str_contains($haystack, 'FILE CAN NOT DOWNLOAD') || str_contains($haystack, 'FILE CANNOT DOWNLOAD')) {
            return ['Header media error', 'Re-upload the header media, or use a public HTTPS URL.', 'The file can not download.'];
        }

        if (str_contains($haystack, 'SAME NAME') || str_contains($haystack, 'ALREADY EXISTS') || str_contains($haystack, 'DUPLICATE')) {
            return ['Duplicate template name', 'Use a different template name and language combination.', null];
        }

        if (
            str_contains($haystack, 'BEING DELETED')
            || str_contains($haystack, 'LANGUAGE IS BEING DELETED')
            || str_contains($haystack, 'TRY AGAIN IN 4 WEEKS')
            || (str_contains($haystack, '4 WEEKS') && str_contains($haystack, 'TEMPLATE'))
        ) {
            return [
                'Template name on cooldown',
                'Choose a new template name, or wait up to 4 weeks before reusing this name + language.',
                null,
            ];
        }

        return match ($codeKey) {
            'MISSINGTYPE' => ['Missing template type', 'Re-check header, body, and buttons, then submit again.', 'Type is mandatory for this action.'],
            'MISSINGCOMPONENTS' => ['Missing template components', 'Save each builder step, then submit again.', 'Components are mandatory for this action.'],
            'INVALIDPARAMETER.FILEURLERROR' => ['Header media error', 'Re-upload the header media, or use a public HTTPS URL.', 'The file can not download.'],
            'INVALIDPARAMETER.FORMAT', 'INVALIDPARAMETER' => ['Invalid parameter', 'Check header media, body text, variables, and buttons.', null],
            'FORBIDDEN.RAM', 'FORBIDDEN', 'RAM.PERMISSIONDENY' => ['Permission denied', 'Contact support to check WhatsApp / Alibaba permissions.', null],
            'THROTTLING.USER', 'SYSTEM.LIMITCONTROL', 'THROTTLING' => ['Rate limited', 'Wait a minute, then submit again.', null],
            'PRODUCT.UNSUBSCRIPT' => ['Service not subscribed', 'Contact support to activate WhatsApp messaging.', null],
            'TEMPLATE.NOTFOUND', 'TEMPLATENOTFOUND' => ['Template not found', null, null],
            default => ['Submission failed', null, null],
        };
    }

    private static function looksLikeMetaAuditReason(string $raw): bool
    {
        if (self::isGenericFiller($raw)) {
            return false;
        }

        if (str_starts_with(ltrim($raw), '{') || str_starts_with(ltrim($raw), '[')) {
            return false;
        }

        if (preg_match('/\bCode\s*[:=]\s*[A-Za-z0-9._-]+/i', $raw) === 1) {
            return false;
        }

        $upper = strtoupper($raw);

        return str_contains($upper, 'MESSAGE TEMPLATE')
            || str_contains($upper, 'BEING DELETED')
            || str_contains($upper, 'TRY AGAIN IN')
            || str_contains($upper, 'CAN\'T BE ADDED')
            || str_contains($upper, 'CANNOT BE ADDED')
            || str_contains($upper, 'CONSIDER CREATING A NEW')
            || str_contains($upper, 'DOES NOT MATCH')
            || str_contains($upper, 'POLICY')
            || str_contains($upper, 'VIOLAT')
            || (mb_strlen($raw) > 80 && ! str_contains($upper, 'INVALIDPARAMETER') && ! str_contains($upper, 'CODE:'));
    }

    private static function titleForMetaReason(string $raw): string
    {
        $upper = strtoupper($raw);

        if (str_contains($upper, 'BEING DELETED') || str_contains($upper, '4 WEEKS')) {
            return 'Template name on cooldown';
        }

        return 'Template rejected';
    }

    private static function hintForMetaReason(string $raw): ?string
    {
        $upper = strtoupper($raw);

        if (str_contains($upper, 'BEING DELETED') || str_contains($upper, '4 WEEKS')) {
            return 'Choose a new template name, or wait up to 4 weeks before reusing this name + language.';
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string} [code, message]
     */
    private static function extractCodeAndMessage(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $code = trim((string) ($decoded['Code'] ?? $decoded['code'] ?? data_get($decoded, 'Data.Code') ?? data_get($decoded, 'data.Code') ?? ''));
            $message = trim((string) (
                $decoded['Message']
                ?? $decoded['message']
                ?? $decoded['Reason']
                ?? $decoded['reason']
                ?? data_get($decoded, 'Data.Message')
                ?? data_get($decoded, 'data.Message')
                ?? data_get($decoded, 'Data.Reason')
                ?? data_get($decoded, 'data.reason')
                ?? data_get($decoded, 'body.Message')
                ?? ''
            ));

            return [$code, $message];
        }

        $code = '';
        if (preg_match('/\bCode:\s*([A-Za-z0-9._-]+)/i', $raw, $match) === 1) {
            $code = trim($match[1]);
        } elseif (preg_match('/\b(InvalidParameter(?:\.[A-Za-z0-9_-]+)?|MissingType|MissingComponents|Forbidden(?:\.[A-Za-z0-9_-]+)?|Throttling(?:\.[A-Za-z0-9_-]+)?)\b/i', $raw, $match) === 1) {
            $code = trim($match[1]);
        }

        $message = self::stripNoise($raw);
        if ($code !== '' && strcasecmp(trim($message, ' .'), $code) === 0) {
            $message = '';
        }

        return [$code, $message];
    }

    private static function polishProviderMessage(string $message): string
    {
        $message = self::stripStoredFiller(self::stripNoise($message));
        if ($message === '') {
            return '';
        }

        $message = rtrim($message, " \t\n\r\0\x0B.,;");
        if ($message === '') {
            return '';
        }

        // Keep Meta wording intact (e.g. English (UK)); only ensure it ends cleanly.
        if (! str_ends_with($message, '.') && ! str_ends_with($message, '!') && ! str_ends_with($message, '?') && ! str_ends_with($message, ')')) {
            $message .= '.';
        }

        return \Illuminate\Support\Str::limit($message, 1000);
    }

    private static function stripNoise(string $text): string
    {
        $text = preg_replace('/\bcode:\s*\d{3},?/i', '', $text) ?? $text;
        $text = preg_replace('/\brequest id:\s*[A-Z0-9-]+/i', '', $text) ?? $text;
        $text = preg_replace('/\bRequestId\s*[:=]\s*[A-Z0-9-]+/i', '', $text) ?? $text;
        // Only strip Code= when it is a CAMS machine code, not prose.
        $text = preg_replace('/\bCode\s*[:=]\s*(InvalidParameter[A-Za-z0-9._-]*|MissingType|MissingComponents|Forbidden[A-Za-z0-9._-]*|Throttling[A-Za-z0-9._-]*)\b/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text, " \t\n\r\0\x0B,;|-");
    }

    private static function stripStoredFiller(string $text): string
    {
        $patterns = [
            '/WhatsApp could not accept this template\.?\s*Please review it and try again\.?/i',
            '/WhatsApp could not accept this template\.?/i',
            '/WhatsApp rejected this template without a detailed reason\.?/i',
            '/WhatsApp rejected this template\.?/i',
            '/No error details were returned by WhatsApp\.?/i',
            '/No rejection details were returned by WhatsApp\.?/i',
            '/Please review it and try again\.?/i',
            '/Edit the template,?\s*then submit again\.?/i',
            '/Edit the template and submit again\.?\s*If it keeps failing, contact support\.?/i',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function normalizeCode(string $code): string
    {
        return strtoupper(str_replace([' ', '_'], '', trim($code)));
    }
}
