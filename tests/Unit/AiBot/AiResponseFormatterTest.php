<?php

namespace Tests\Unit\AiBot;

use App\Domains\AiBot\Support\AiResponseFormatter;
use PHPUnit\Framework\TestCase;

class AiResponseFormatterTest extends TestCase
{
    public function test_converts_markdown_bold_to_whatsapp(): void
    {
        $result = AiResponseFormatter::forWhatsApp('This is **bold** text');

        $this->assertSame('This is *bold* text', $result);
    }

    public function test_strips_code_blocks(): void
    {
        $result = AiResponseFormatter::forWhatsApp("Hello ```php\necho 'hi';\n``` world");

        $this->assertSame('Hello  world', $result);
    }

    public function test_truncates_long_messages(): void
    {
        $longText = str_repeat('A', 5000);
        $result = AiResponseFormatter::forWhatsApp($longText);

        $this->assertLessThanOrEqual(4000, mb_strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    public function test_normalizes_excessive_newlines(): void
    {
        $result = AiResponseFormatter::forWhatsApp("Line 1\n\n\n\n\nLine 2");

        $this->assertSame("Line 1\n\nLine 2", $result);
    }

    public function test_handles_empty_string(): void
    {
        $result = AiResponseFormatter::forWhatsApp('');

        $this->assertSame('', $result);
    }
}
