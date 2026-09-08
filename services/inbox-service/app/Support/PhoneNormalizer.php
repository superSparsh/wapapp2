<?php

declare(strict_types=1);

namespace App\Support;

final class PhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return $digits;
    }

    /**
     * @return list<string>
     */
    public static function lookupVariants(string $search): array
    {
        $normalized = self::normalize($search);

        if ($normalized === null || $normalized === '') {
            return [];
        }

        $variants = [$normalized];

        if (strlen($normalized) === 10) {
            $variants[] = '91'.$normalized;
        } elseif (strlen($normalized) === 12 && str_starts_with($normalized, '91')) {
            $variants[] = substr($normalized, 2);
        }

        return array_values(array_unique($variants));
    }
}
