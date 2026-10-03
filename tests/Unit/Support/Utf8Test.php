<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Utf8;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Utf8Test extends TestCase
{
    #[Test]
    public function it_preserves_valid_utf8(): void
    {
        $this->assertSame('नमस्ते 👋', Utf8::clean('नमस्ते 👋'));
    }

    #[Test]
    public function it_scrubs_invalid_utf8_bytes(): void
    {
        $cleaned = Utf8::clean("Bad\xC0Name");

        $this->assertTrue(mb_check_encoding($cleaned, 'UTF-8'));
        $this->assertStringContainsString('Bad', $cleaned);
        $this->assertStringContainsString('Name', $cleaned);
    }

    #[Test]
    public function it_deep_cleans_nested_arrays(): void
    {
        $payload = [
            'name' => "Bad\xC0Name",
            'items' => [
                ['preview' => "Hi\xFF"],
            ],
        ];

        $cleaned = Utf8::deepClean($payload);

        $this->assertTrue(mb_check_encoding($cleaned['name'], 'UTF-8'));
        $this->assertTrue(mb_check_encoding($cleaned['items'][0]['preview'], 'UTF-8'));
        $this->assertNotFalse(json_encode($cleaned));
    }
}
