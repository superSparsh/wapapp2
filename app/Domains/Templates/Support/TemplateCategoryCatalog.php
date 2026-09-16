<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

/**
 * Template categories for the builder / listing.
 *
 * Legacy parity: "Carousel" is NOT a WhatsApp category. Selecting Carousel stores
 * MARKETING + payload.carousel.enabled=true (legacy: category_id=2 + is_carousel_template=1).
 * LTO stays as LIMITED_TIME_OFFER locally but submits to WhatsApp as MARKETING.
 */
final class TemplateCategoryCatalog
{
    public const MARKETING = 'MARKETING';

    public const UTILITY = 'UTILITY';

    public const AUTHENTICATION = 'AUTHENTICATION';

    public const LIMITED_TIME_OFFER = 'LIMITED_TIME_OFFER';

    /** UI-only option value — never persist as the WhatsApp/local category column. */
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

    /**
     * Map a builder select value to the stored template.category column.
     * Carousel → MARKETING (legacy new_template_category_id = 2).
     */
    public static function storedCategory(string $selected): string
    {
        $selected = strtoupper(trim($selected));

        if ($selected === self::CAROUSEL) {
            return self::MARKETING;
        }

        return $selected !== '' ? $selected : self::MARKETING;
    }

    /**
     * Value for the category <select> / display when editing.
     * Marketing + carousel.enabled → show Carousel option selected.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function uiCategory(string $storedCategory, array $payload = []): string
    {
        $stored = strtoupper(trim($storedCategory));

        if ($stored === self::CAROUSEL) {
            return self::CAROUSEL;
        }

        if ($stored === self::MARKETING && (bool) data_get($payload, 'carousel.enabled', false)) {
            return self::CAROUSEL;
        }

        return $stored !== '' ? $stored : self::MARKETING;
    }

    /**
     * Listing chip: carousel templates show Marketing (legacy list parity).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function listLabel(string $storedCategory, array $payload = []): string
    {
        $stored = strtoupper(trim($storedCategory));

        if ($stored === self::CAROUSEL || (bool) data_get($payload, 'carousel.enabled', false)) {
            return self::labels()[self::MARKETING];
        }

        return self::label($stored);
    }

    public static function isCarouselSelection(string $selected): bool
    {
        return strtoupper(trim($selected)) === self::CAROUSEL;
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

    /** WhatsApp / CAMS Category field. */
    public static function whatsAppCategory(string $category): string
    {
        return match (strtoupper($category)) {
            self::LIMITED_TIME_OFFER, self::CAROUSEL => self::MARKETING,
            default => strtoupper($category) !== '' ? strtoupper($category) : self::MARKETING,
        };
    }
}
