<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use App\Domains\Templates\Support\WhatsAppTextFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class WhatsAppTextFormatterTest extends TestCase
{
    #[Test]
    public function it_formats_legacy_and_whatsapp_markers(): void
    {
        $html = WhatsAppTextFormatter::toHtml('Hello ^bold^ and *also* _italic_ ~strike~');

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<strong>also</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
        $this->assertStringContainsString('<del>strike</del>', $html);
    }

    #[Test]
    public function it_escapes_html_and_preserves_line_breaks(): void
    {
        $html = WhatsAppTextFormatter::toHtml("<script>alert(1)</script>\nNext");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<br>', $html);
    }
}
