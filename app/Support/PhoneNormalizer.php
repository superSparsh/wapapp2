<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }

    /** @return array<int, string> */
    public static function lookupVariants(?string $phone): array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return [];
        }

        $normalized = self::normalize($phone);
        $variants = array_values(array_filter([
            $normalized,
            $digits,
        ]));

        if (strlen($digits) >= 10) {
            $last10 = substr($digits, -10);
            $variants[] = $last10;
            $variants[] = '91'.$last10;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $variants[] = substr($digits, 1);
            $variants[] = '91'.substr($digits, 1);
        }

        return array_values(array_unique($variants));
    }

    public static function isValidIndianMobile(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return (bool) preg_match('/^[6-9]\d{9}$/', strlen($digits) === 12 && str_starts_with($digits, '91')
            ? substr($digits, 2)
            : $digits);
    }
}
