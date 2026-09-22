<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services;

final class WhatsAppHealthTierHelper
{
    public const STALE_PENDING_HOURS = 24;

    public static function dailyLimit(?string $messageLimiter, ?string $lineStatus = null): int
    {
        $tier = strtoupper(trim((string) $messageLimiter));
        if ($tier === '' || $tier === 'UNKNOWN') {
            return 250;
        }

        if (preg_match('/TIER[_\s-]*(\d+)\s*K/i', $tier, $m)) {
            return (int) $m[1] * 1000;
        }

        if (preg_match('/TIER[_\s-]*(\d+)/i', $tier, $m)) {
            return (int) $m[1];
        }

        if (ctype_digit($tier)) {
            return (int) $tier;
        }

        return 250;
    }

    public static function usagePercent(int $used, int $limit): ?float
    {
        if ($limit <= 0) {
            return null;
        }

        return min(100.0, round(($used / $limit) * 100, 1));
    }

    public static function normalizeTemplateStatus(?string $status): string
    {
        return strtoupper(trim((string) $status));
    }

    public static function isRejectedTemplateStatus(?string $status): bool
    {
        $s = self::normalizeTemplateStatus($status);

        return $s !== '' && (str_contains($s, 'REJECT') || $s === 'DISABLED' || $s === 'PAUSED' || $s === 'FAILED');
    }

    public static function isPendingTemplateStatus(?string $status): bool
    {
        $s = self::normalizeTemplateStatus($status);

        return $s !== '' && (str_contains($s, 'PENDING') || $s === 'IN_APPEAL' || $s === 'SUBMITTED');
    }
}
