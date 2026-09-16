<?php

declare(strict_types=1);

namespace App\Domains\WhatsApp\Support;

/**
 * Turns raw Alibaba CAMS / WhatsApp template errors into short, actionable UI copy.
 */
final class CamsErrorPresenter
{
    /**
     * @return array{title: string, message: string, hint: string|null}
     */
    public static function present(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [
                'title' => 'Submission failed',
                'message' => 'WhatsApp could not accept this template. Please review it and try again.',
                'hint' => 'Edit the template, then submit again.',
            ];
        }

        [$code, $message] = self::extractCodeAndMessage($raw);
        $codeKey = self::normalizeCode($code);
        $haystack = strtoupper($code.' '.$message.' '.$raw);

        if (str_contains($haystack, 'FILEURLERROR') || str_contains($haystack, 'FILE CAN NOT DOWNLOAD') || str_contains($haystack, 'FILE CANNOT DOWNLOAD')) {
            return [
                'title' => 'Header media unavailable',
                'message' => 'WhatsApp could not download the header image, video, or document.',
                'hint' => 'Re-upload the media file (or use a public HTTPS URL), then submit again.',
            ];
        }

        if (str_contains($haystack, 'SAME NAME') || str_contains($haystack, 'ALREADY EXISTS') || str_contains($haystack, 'DUPLICATE')) {
            return [
                'title' => 'Template name already used',
                'message' => 'A WhatsApp template with this name and language already exists.',
                'hint' => 'Choose a different template name and submit again.',
            ];
        }

        $mapped = match ($codeKey) {
            'MISSINGTYPE' => [
                'title' => 'Template structure incomplete',
                'message' => 'A required template section (header, body, or buttons) was missing type information.',
                'hint' => 'Open the builder, re-check each step, and submit again.',
            ],
            'MISSINGCOMPONENTS' => [
                'title' => 'Template content missing',
                'message' => 'WhatsApp did not receive the template components.',
                'hint' => 'Edit the body/header/buttons, save each step, then submit again.',
            ],
            'INVALIDPARAMETER.FILEURLERROR' => [
                'title' => 'Header media unavailable',
                'message' => 'WhatsApp could not download the header image, video, or document.',
                'hint' => 'Re-upload the media file (or use a public HTTPS URL), then submit again.',
            ],
            'INVALIDPARAMETER.FORMAT', 'INVALIDPARAMETER' => [
                'title' => 'Invalid template details',
                'message' => self::polishMessage($message) ?: 'One or more template fields are not valid for WhatsApp.',
                'hint' => 'Check header media, body text, variables, and buttons, then submit again.',
            ],
            'FORBIDDEN.RAM', 'FORBIDDEN', 'RAM.PERMISSIONDENY' => [
                'title' => 'WhatsApp account permission error',
                'message' => 'Your WhatsApp Business account does not have permission for this action.',
                'hint' => 'Contact support so the Alibaba / WhatsApp permissions can be checked.',
            ],
            'THROTTLING.USER', 'SYSTEM.LIMITCONTROL', 'THROTTLING' => [
                'title' => 'Too many requests',
                'message' => 'WhatsApp is rate-limiting template submissions right now.',
                'hint' => 'Wait a minute, then submit again.',
            ],
            'PRODUCT.UNSUBSCRIPT' => [
                'title' => 'WhatsApp service not active',
                'message' => 'The Chat App / WhatsApp product is not subscribed on this account.',
                'hint' => 'Contact support to activate WhatsApp messaging.',
            ],
            'TEMPLATE.NOTFOUND', 'TEMPLATENOTFOUND' => [
                'title' => 'Template not found on WhatsApp',
                'message' => 'WhatsApp could not find this template to update.',
                'hint' => 'Create a new template, or contact support if this keeps happening.',
            ],
            default => null,
        };

        if ($mapped !== null) {
            return $mapped;
        }

        $polished = self::polishMessage($message !== '' ? $message : $raw);
        if ($polished === '') {
            $polished = 'WhatsApp could not accept this template.';
        }

        return [
            'title' => 'Submission failed',
            'message' => $polished,
            'hint' => 'Edit the template and submit again. If it keeps failing, contact support.',
        ];
    }

    public static function friendlyMessage(?string $raw): string
    {
        $presented = self::present($raw);
        $parts = array_filter([
            $presented['message'],
            $presented['hint'],
        ], static fn (?string $part): bool => filled($part));

        return \Illuminate\Support\Str::limit(implode(' ', $parts), 360);
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

        // Tea SDK style: "code: 400, The file can not download. request id: ABC-123 Code: InvalidParameter.FileUrlError"
        $code = '';
        if (preg_match('/\bCode:\s*([A-Za-z0-9._-]+)/i', $raw, $match) === 1) {
            $code = trim($match[1]);
        } elseif (preg_match('/\b([A-Za-z]+(?:\.[A-Za-z0-9_-]+)+)\b/', $raw, $match) === 1) {
            $code = trim($match[1]);
        }

        $message = preg_replace('/\bcode:\s*\d{3},?/i', '', $raw) ?? $raw;
        $message = preg_replace('/\brequest id:\s*[A-Z0-9-]+/i', '', $message) ?? $message;
        $message = preg_replace('/\bCode:\s*[A-Za-z0-9._-]+/i', '', $message) ?? $message;
        $message = preg_replace('/\bRequestId:\s*[A-Z0-9-]+/i', '', $message) ?? $message;
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        return [$code, $message];
    }

    private static function normalizeCode(string $code): string
    {
        return strtoupper(str_replace([' ', '_'], '', trim($code)));
    }

    private static function polishMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        // Already polished earlier — keep as-is.
        if (str_starts_with($message, 'WhatsApp ') || str_starts_with($message, 'A WhatsApp ')) {
            return \Illuminate\Support\Str::limit($message, 280);
        }

        $message = preg_replace('/\bcode:\s*\d{3},?/i', '', $message) ?? $message;
        $message = preg_replace('/\brequest id:\s*[A-Z0-9-]+/i', '', $message) ?? $message;
        $message = preg_replace('/\bRequestId:\s*[A-Z0-9-]+/i', '', $message) ?? $message;
        $message = trim((string) preg_replace('/\s+/', ' ', $message));
        $message = rtrim($message, " \t\n\r\0\x0B.,;");

        if ($message === '') {
            return '';
        }

        // Capitalize first letter for UI readability.
        $message = mb_strtoupper(mb_substr($message, 0, 1)).mb_substr($message, 1);
        if (! str_ends_with($message, '.') && ! str_ends_with($message, '!') && ! str_ends_with($message, '?')) {
            $message .= '.';
        }

        return \Illuminate\Support\Str::limit($message, 280);
    }
}
