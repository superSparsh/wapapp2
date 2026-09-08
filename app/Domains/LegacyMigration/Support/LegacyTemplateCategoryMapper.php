<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Support;

use App\Domains\Templates\Support\TemplateCategoryCatalog;

/**
 * Maps legacy new_templates category fields to wapapp-2.0 TemplateCategoryCatalog values.
 *
 * Legacy stores WhatsApp category on new_templates.new_template_category_id
 * → new_template_categories.category_name (not a free-text "category" column).
 *
 * Known IDs (from legacy UI / CampaignService):
 * 2,3 = Marketing (3 often carousel/LTO UI, still billed as marketing)
 * 4 = Utility
 * 5 = Authentication
 * 6 = Service (mapped to Utility for Meta template categories)
 */
final class LegacyTemplateCategoryMapper
{
    public static function fromLegacyRow(object $row): string
    {
        if ((int) ($row->is_carousel_template ?? 0) === 1) {
            return TemplateCategoryCatalog::CAROUSEL;
        }

        $rawName = trim((string) (
            $row->legacy_category_name
            ?? $row->category_name
            ?? $row->category
            ?? $row->template_category
            ?? $row->template_type
            ?? ''
        ));

        if ($rawName !== '') {
            return self::fromLabel($rawName);
        }

        return match ((int) ($row->new_template_category_id ?? 0)) {
            2, 3 => TemplateCategoryCatalog::MARKETING,
            4 => TemplateCategoryCatalog::UTILITY,
            5 => TemplateCategoryCatalog::AUTHENTICATION,
            6 => TemplateCategoryCatalog::UTILITY,
            default => TemplateCategoryCatalog::MARKETING,
        };
    }

    public static function fromLabel(mixed $raw): string
    {
        $value = strtoupper(trim((string) ($raw ?? '')));

        if ($value === '') {
            return TemplateCategoryCatalog::MARKETING;
        }

        return match (true) {
            str_contains($value, 'CAROUSEL') => TemplateCategoryCatalog::CAROUSEL,
            str_contains($value, 'LTO') || str_contains($value, 'LIMITED') => TemplateCategoryCatalog::LIMITED_TIME_OFFER,
            str_contains($value, 'AUTH') => TemplateCategoryCatalog::AUTHENTICATION,
            str_contains($value, 'UTIL') || str_contains($value, 'SERVICE') => TemplateCategoryCatalog::UTILITY,
            str_contains($value, 'MARKET') => TemplateCategoryCatalog::MARKETING,
            default => TemplateCategoryCatalog::MARKETING,
        };
    }
}
