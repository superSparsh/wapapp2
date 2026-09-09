<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonInterface;

if (! function_exists('ist_timezone')) {
    function ist_timezone(): string
    {
        return 'Asia/Kolkata';
    }
}

if (! function_exists('format_ist')) {
    /**
     * Format any datetime / UNIX timestamp for display in IST (Asia/Kolkata).
     *
     * Accepts Carbon, DateTimeInterface, datetime strings, or UNIX seconds/ms.
     */
    function format_ist(mixed $value, string $format = 'd M Y, h:i A'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $tz = ist_timezone();

        try {
            if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $ts = (int) $value;
                // Milliseconds (e.g. JS Date.now())
                if ($ts > 1_000_000_000_000) {
                    $ts = (int) floor($ts / 1000);
                }

                return Carbon::createFromTimestamp($ts, 'UTC')
                    ->timezone($tz)
                    ->format($format);
            }

            if ($value instanceof CarbonInterface) {
                return $value->copy()->timezone($tz)->format($format);
            }

            if ($value instanceof DateTimeInterface) {
                return Carbon::instance(DateTimeImmutable::createFromInterface($value))
                    ->timezone($tz)
                    ->format($format);
            }

            return Carbon::parse((string) $value)->timezone($tz)->format($format);
        } catch (Throwable) {
            return '—';
        }
    }
}
