<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

final class TemplateCategoryCatalog
{
    public const MARKETING = 'MARKETING';

    public const UTILITY = 'UTILITY';

    public const AUTHENTICATION = 'AUTHENTICATION';

    public const LIMITED_TIME_OFFER = 'LIMITED_TIME_OFFER';

    public const CAROUSEL = 'CAROUSEL';

    /** @return list<string> */
    public static function builderValues(): array
    {
        return [
            self::MARKETING,
            self::UTILITY,
            self::AUTHENTICATION,
            self::LIMITED_TIME_OFFER,
            self::CAROUSEL,
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::MARKETING => 'Marketing',
            self::UTILITY => 'Utility',
            self::AUTHENTICATION => 'Authentication',
            self::LIMITED_TIME_OFFER => 'Limited Time Offer',
            self::CAROUSEL => 'Carousel',
        ];
    }

    public static function label(string $category): string
    {
        return self::labels()[strtoupper($category)] ?? ucfirst(strtolower($category));
    }

    public static function isCarousel(string $category): bool
    {
        return strtoupper($category) === self::CAROUSEL;
    }

    public static function isLto(string $category): bool
    {
        return strtoupper($category) === self::LIMITED_TIME_OFFER;
    }

    public static function isAuthentication(string $category): bool
    {
        return strtoupper($category) === self::AUTHENTICATION;
    }

    /** WhatsApp API category for submission. */
    public static function whatsAppCategory(string $category): string
    {
        return match (strtoupper($category)) {
            self::LIMITED_TIME_OFFER, self::CAROUSEL => self::MARKETING,
            default => strtoupper($category),
        };
    }
}
