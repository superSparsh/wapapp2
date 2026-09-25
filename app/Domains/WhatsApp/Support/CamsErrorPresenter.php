<?php

declare(strict_types=1);

namespace App\Domains\WhatsApp\Support;

/**
 * Presents Alibaba CAMS / WhatsApp template errors for the UI.
 * Prefers the real provider Message (cleaned); never invents vague filler copy.
 */
final class CamsErrorPresenter
{
    /**
     * @return array{title: string, message: string, hint: string|null}
     */
    public static function present(?string $raw): array
    {
        $raw = self::stripStoredFiller(trim((string) $raw));
        if ($raw === '') {
            return [
                'title' => 'Submission failed',
                'message' => 'No error details were returned by WhatsApp.',
                'hint' => null,
            ];
        }

        [$code, $message] = self::extractCodeAndMessage($raw);
        $codeKey = self::normalizeCode($code);
        $providerMessage = self::polishProviderMessage($message !== '' ? $message : $raw);
        $haystack = strtoupper($code.' '.$message.' '.$raw);

        // Title + optional hint from known codes; body stays the real Alibaba/Meta text when present.
        [$title, $hint, $fallbackMessage] = self::guidanceFor($codeKey, $haystack);

        $body = $providerMessage !== '' ? $providerMessage : ($fallbackMessage ?? '');
        if ($body === '') {
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

        // Store / toast: real provider message first; hint only when it adds action.
        $parts = array_filter([
            $presented['message'],
            $presented['hint'],
        ], static fn (?string $part): bool => filled($part));

        return \Illuminate\Support\Str::limit(implode(' ', $parts), 360);
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
            || str_contains($haystack, '4 WEEKS')
        ) {
            return [
                'Template name on cooldown',
                'WhatsApp blocks reusing a deleted template name + language for up to 4 weeks. Create the template under a new name.',
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

    /**
     * @return array{0: string, 1: string} [code, message]
     */
    private static function extractCodeAndMessage(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $code = trim((string) ($decoded['Code'] ?? $decoded['code'] ?? ''));
            $message = trim((string) ($decoded['Message'] ?? $decoded['message'] ?? $decoded['Reason'] ?? $decoded['reason'] ?? ''));

            return [$code, $message];
        }

        $code = '';
        if (preg_match('/\bCode:\s*([A-Za-z0-9._-]+)/i', $raw, $match) === 1) {
            $code = trim($match[1]);
        } elseif (preg_match('/\b(InvalidParameter(?:\.[A-Za-z0-9_-]+)?|MissingType|MissingComponents|Forbidden(?:\.[A-Za-z0-9_-]+)?|Throttling(?:\.[A-Za-z0-9_-]+)?)\b/i', $raw, $match) === 1) {
            $code = trim($match[1]);
        }

        $message = self::stripNoise($raw);
        // If the only content left is the code itself, treat message as empty.
        if ($code !== '' && strcasecmp(trim($message, " ."), $code) === 0) {
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

        $message = mb_strtoupper(mb_substr($message, 0, 1)).mb_substr($message, 1);
        if (! str_ends_with($message, '.') && ! str_ends_with($message, '!') && ! str_ends_with($message, '?')) {
            $message .= '.';
        }

        return \Illuminate\Support\Str::limit($message, 280);
    }

    private static function stripNoise(string $text): string
    {
        $text = preg_replace('/\bcode:\s*\d{3},?/i', '', $text) ?? $text;
        $text = preg_replace('/\brequest id:\s*[A-Z0-9-]+/i', '', $text) ?? $text;
        $text = preg_replace('/\bRequestId\s*[:=]\s*[A-Z0-9-]+/i', '', $text) ?? $text;
        $text = preg_replace('/\bCode\s*[:=]\s*[A-Za-z0-9._-]+/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text, " \t\n\r\0\x0B,;|-");
    }

    private static function stripStoredFiller(string $text): string
    {
        // Drop previously saved vague filler so the UI does not keep recycling it.
        $patterns = [
            '/WhatsApp could not accept this template\.?\s*Please review it and try again\.?/i',
            '/WhatsApp could not accept this template\.?/i',
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
