<?php

declare(strict_types=1);

namespace App\Support;

final class Utf8
{
    public static function clean(?string $value): string
    {
        if ($value === null || $value === '') {
            return (string) ($value ?? '');
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_scrub($value, 'UTF-8');
    }

    public static function deepClean(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::clean($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $cleaned = [];

        foreach ($value as $key => $item) {
            $cleanKey = is_string($key) ? self::clean($key) : $key;
            $cleaned[$cleanKey] = self::deepClean($item);
        }

        return $cleaned;
    }
}
