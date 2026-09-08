<?php

declare(strict_types=1);

namespace App\Support;

class PhoneNormalizer
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $cleaned = preg_replace('/[^\d]/', '', $phone);

        if ($cleaned === null || $cleaned === '') {
            return null;
        }

        return $cleaned;
    }
}
