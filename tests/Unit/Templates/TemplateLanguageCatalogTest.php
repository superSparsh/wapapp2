<?php

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\TemplateLanguageCatalog;
use Tests\TestCase;

class TemplateLanguageCatalogTest extends TestCase
{
    public function test_it_returns_configured_language_options(): void
    {
        $options = TemplateLanguageCatalog::options();

        $this->assertArrayHasKey('en_GB', $options);
        $this->assertArrayHasKey('hi_IN', $options);
        $this->assertSame('English (UK)', TemplateLanguageCatalog::label('en_GB'));
    }
}
