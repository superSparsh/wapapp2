<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class MigrationSinceParser
{
    /**
     * Accepts: 3months | 3m | 90d | 90days | 2025-07-01 | 2025-07-01 00:00:00
     */
    public static function parse(?string $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $lower = strtolower($raw);

        if (preg_match('/^(\d+)\s*(m|months?)$/', $lower, $m) === 1) {
            return now()->subMonths((int) $m[1])->startOfDay();
        }

        if (preg_match('/^(\d+)\s*(d|days?)$/', $lower, $m) === 1) {
            return now()->subDays((int) $m[1])->startOfDay();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                "Invalid --since value [{$raw}]. Use 3months, 90d, or YYYY-MM-DD."
            );
        }
    }
}
