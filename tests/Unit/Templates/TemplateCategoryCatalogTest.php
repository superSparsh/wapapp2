<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\TemplateCategoryCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TemplateCategoryCatalogTest extends TestCase
{
    #[Test]
    public function carousel_selection_stores_as_marketing_with_ui_option(): void
    {
        $this->assertSame(
            TemplateCategoryCatalog::MARKETING,
            TemplateCategoryCatalog::storedCategory(TemplateCategoryCatalog::CAROUSEL)
        );

        $this->assertSame(
            TemplateCategoryCatalog::CAROUSEL,
            TemplateCategoryCatalog::uiCategory(TemplateCategoryCatalog::MARKETING, [
                'carousel' => ['enabled' => true],
            ])
        );

        $this->assertSame(
            'Marketing',
            TemplateCategoryCatalog::listLabel(TemplateCategoryCatalog::MARKETING, [
                'carousel' => ['enabled' => true],
            ])
        );

        $this->assertSame(
            'Marketing',
            TemplateCategoryCatalog::listLabel(TemplateCategoryCatalog::CAROUSEL)
        );

        $this->assertSame(
            TemplateCategoryCatalog::MARKETING,
            TemplateCategoryCatalog::whatsAppCategory(TemplateCategoryCatalog::CAROUSEL)
        );
    }

    #[Test]
    public function marketing_without_carousel_flag_stays_marketing(): void
    {
        $this->assertSame(
            TemplateCategoryCatalog::MARKETING,
            TemplateCategoryCatalog::uiCategory(TemplateCategoryCatalog::MARKETING, [
                'carousel' => ['enabled' => false],
            ])
        );

        $this->assertSame(
            'Marketing',
            TemplateCategoryCatalog::listLabel(TemplateCategoryCatalog::MARKETING)
        );
    }
}
