<?php

declare(strict_types=1);

namespace App\Domains\AiBot\Support;

/**
 * Detects AI provider exhaustion / cooldown errors and builds Test Bot copy.
 * WhatsApp customer copy comes from AiSetting (see AiChatService).
 */
final class AiTestBotUserMessage
{
    public const PROVIDER_UNAVAILABLE = 'AI providers are exhausted right now and cooling down. Please try again in a little while — we\'ll get back to you shortly.';

    public static function isProviderUnavailable(string $raw): bool
    {
        $haystack = mb_strtolower($raw);

        $needles = [
            'all_providers_exhausted',
            'providers_exhausted',
            'cooling down',
            'coolingdown',
            'proactive limit reached',
            'prepayment credits are depleted',
            'credits are depleted',
            'http 402',
            '"error":"all_providers_exhausted"',
        ];

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return str_contains($haystack, 'tittu gateway chat failed')
            && (
                str_contains($haystack, 'exhausted')
                || str_contains($haystack, 'depleted')
                || str_contains($haystack, 'limit')
                || str_contains($haystack, '402')
                || str_contains($haystack, 'cooling')
            );
    }

    /**
     * Map raw provider/gateway errors to a calm Test Bot reply when appropriate.
     */
    public static function forTestBot(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (! self::isProviderUnavailable($raw)) {
            return null;
        }

        $seconds = self::extractCooldownSeconds($raw);
        if ($seconds !== null && $seconds > 0) {
            return 'AI providers are exhausted right now and cooling down. It should be ready again in about '
                .self::formatDuration($seconds)
                .'. We\'ll get back to you shortly.';
        }

        return self::PROVIDER_UNAVAILABLE;
    }

    /**
     * Best-effort cooldown extraction from gateway JSON / error text.
     */
    public static function extractCooldownSeconds(string $raw): ?int
    {
        $payload = self::extractJsonPayload($raw);
        if (is_array($payload)) {
            $fromJson = self::cooldownFromArray($payload);
            if ($fromJson !== null) {
                return $fromJson;
            }
        }

        // Direct field snippets if JSON was truncated.
        if (preg_match('/"cooldownRemainingSeconds"\s*:\s*(\d+)/i', $raw, $m)
            || preg_match('/"cooldown_seconds"\s*:\s*(\d+)/i', $raw, $m)
            || preg_match('/"retryAfter(?:Seconds)?"\s*:\s*(\d+)/i', $raw, $m)
            || preg_match('/"retry_after(?:_seconds)?"\s*:\s*(\d+)/i', $raw, $m)
            || preg_match('/"coolingDownSeconds"\s*:\s*(\d+)/i', $raw, $m)
        ) {
            return max(1, (int) $m[1]);
        }

        // Human text: "45s left", "try again in 2 minutes", "cooldown 90 seconds"
        if (preg_match('/(\d+)\s*s(?:ec(?:onds?)?)?\s*left/i', $raw, $m)
            || preg_match('/cooldown[^0-9]{0,20}(\d+)\s*s(?:ec(?:onds?)?)?/i', $raw, $m)
            || preg_match('/try again in\s+(\d+)\s*s(?:ec(?:onds?)?)?/i', $raw, $m)
        ) {
            return max(1, (int) $m[1]);
        }

        if (preg_match('/try again in\s+(\d+)\s*min(?:ute)?s?/i', $raw, $m)
            || preg_match('/(\d+)\s*min(?:ute)?s?\s*left/i', $raw, $m)
        ) {
            return max(60, (int) $m[1] * 60);
        }

        return null;
    }

    public static function formatDuration(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds >= 3600) {
            $hours = (int) round($seconds / 3600);
            $hours = max(1, $hours);

            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        if ($seconds >= 60) {
            $minutes = (int) round($seconds / 60);
            $minutes = max(1, $minutes);

            return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
        }

        return $seconds === 1 ? '1 second' : "{$seconds} seconds";
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function extractJsonPayload(string $raw): ?array
    {
        $trimmed = trim($raw);
        if ($trimmed !== '' && ($trimmed[0] ?? '') === '{') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // e.g. "Tittu Gateway chat failed: {...}"
        if (preg_match('/(\{.*\})/s', $raw, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function cooldownFromArray(array $payload): ?int
    {
        $keys = [
            'cooldownRemainingSeconds',
            'cooldown_remaining_seconds',
            'cooldown_seconds',
            'cooldownSeconds',
            'coolingDownSeconds',
            'cooling_down_seconds',
            'retryAfterSeconds',
            'retry_after_seconds',
            'retryAfter',
            'retry_after',
        ];

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                $value = (int) $payload[$key];
                if ($value > 0) {
                    return $value;
                }
            }
        }

        // Nested provider attempts: take the max remaining cooldown we can find.
        $max = 0;
        array_walk_recursive($payload, static function (mixed $value, mixed $key) use (&$max): void {
            if (! is_string($key) && ! is_int($key)) {
                return;
            }
            $name = strtolower((string) $key);
            if (! str_contains($name, 'cooldown') && ! str_contains($name, 'retry')) {
                return;
            }
            if (! is_numeric($value)) {
                return;
            }
            $seconds = (int) $value;
            if ($seconds > $max) {
                $max = $seconds;
            }
        });

        return $max > 0 ? $max : null;
    }
}
