<?php

declare(strict_types=1);

namespace Tests\Unit\LegacyMigration;

use App\Domains\LegacyMigration\Support\LegacyTemplateCategoryMapper;
use App\Domains\Templates\Support\TemplateCategoryCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyTemplateCategoryMapperTest extends TestCase
{
    #[DataProvider('legacyRowProvider')]
    public function test_maps_legacy_row_to_catalog_category(object $row, string $expected): void
    {
        $this->assertSame($expected, LegacyTemplateCategoryMapper::fromLegacyRow($row));
    }

    /**
     * @return array<string, array{0: object, 1: string}>
     */
    public static function legacyRowProvider(): array
    {
        return [
            'by category name utility' => [
                (object) ['legacy_category_name' => 'Utility'],
                TemplateCategoryCatalog::UTILITY,
            ],
            'by category name auth' => [
                (object) ['category_name' => 'Authentication'],
                TemplateCategoryCatalog::AUTHENTICATION,
            ],
            'by category name marketing' => [
                (object) ['legacy_category_name' => 'Marketing'],
                TemplateCategoryCatalog::MARKETING,
            ],
            'by category name service → utility' => [
                (object) ['legacy_category_name' => 'Service'],
                TemplateCategoryCatalog::UTILITY,
            ],
            'id 2 marketing' => [
                (object) ['new_template_category_id' => 2],
                TemplateCategoryCatalog::MARKETING,
            ],
            'id 3 marketing' => [
                (object) ['new_template_category_id' => 3],
                TemplateCategoryCatalog::MARKETING,
            ],
            'id 4 utility' => [
                (object) ['new_template_category_id' => 4],
                TemplateCategoryCatalog::UTILITY,
            ],
            'id 5 authentication' => [
                (object) ['new_template_category_id' => 5],
                TemplateCategoryCatalog::AUTHENTICATION,
            ],
            'id 6 service → utility' => [
                (object) ['new_template_category_id' => 6],
                TemplateCategoryCatalog::UTILITY,
            ],
            'carousel flag wins' => [
                (object) [
                    'new_template_category_id' => 2,
                    'is_carousel_template' => 1,
                ],
                TemplateCategoryCatalog::CAROUSEL,
            ],
            'missing everything defaults marketing' => [
                (object) [],
                TemplateCategoryCatalog::MARKETING,
            ],
        ];
    }
}
