<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\CamsTemplateIdentity;
use Tests\TestCase;

class CamsTemplateIdentityTest extends TestCase
{
    public function test_language_maps_bare_en_to_en_gb_like_legacy(): void
    {
        $this->assertSame('en_GB', CamsTemplateIdentity::language('en'));
        $this->assertSame('en_GB', CamsTemplateIdentity::language('EN'));
        $this->assertSame('en_GB', CamsTemplateIdentity::language(''));
        $this->assertSame('en_GB', CamsTemplateIdentity::language(null));
        $this->assertSame('en_US', CamsTemplateIdentity::language('en_US'));
        $this->assertSame('hi', CamsTemplateIdentity::language('hi'));
    }

    public function test_code_rejects_legacy_placeholder_and_blank(): void
    {
        $this->assertSame('ABC123', CamsTemplateIdentity::code('ABC123'));
        $this->assertSame('ABC123', CamsTemplateIdentity::code('welcome_legacy_9', 'ABC123'));
        $this->assertNull(CamsTemplateIdentity::code('welcome_legacy_9'));
        $this->assertNull(CamsTemplateIdentity::code(''));
        $this->assertNull(CamsTemplateIdentity::code('has spaces'));
    }
}
