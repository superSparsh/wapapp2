<?php

declare(strict_types=1);

namespace Tests\Unit\AiBot;

use App\Domains\AiBot\Support\AiResponseFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiResponseFormatterStripBookingLinksTest extends TestCase
{
    #[Test]
    public function it_strips_calendly_urls_and_booking_cta(): void
    {
        $input = "I'm sorry to hear you're running into trouble with your campaigns! "
            ."To get this sorted quickly, let's have one of our experts take a look—"
            ."please book a time that works for you right here: "
            .'https://calendly.com/tekpro-connect/25min?month=2026-01';

        $out = AiResponseFormatter::stripBookingLinks($input);

        $this->assertStringNotContainsString('calendly.com', $out);
        $this->assertStringNotContainsString('tekpro-connect', $out);
        $this->assertStringNotContainsString('book a time', mb_strtolower($out));
        $this->assertStringContainsString('campaigns', mb_strtolower($out));
    }
}
