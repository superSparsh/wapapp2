<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormatIstTest extends TestCase
{
    #[Test]
    public function it_formats_unix_seconds_in_ist(): void
    {
        // 2024-01-15 12:00:00 UTC → 17:30 IST
        $formatted = format_ist(1705320000, 'Y-m-d H:i');

        $this->assertSame('2024-01-15 17:30', $formatted);
    }

    #[Test]
    public function it_formats_carbon_in_ist(): void
    {
        $utc = Carbon::parse('2024-06-01 06:00:00', 'UTC');
        $formatted = format_ist($utc, 'Y-m-d H:i');

        $this->assertSame('2024-06-01 11:30', $formatted);
    }

    #[Test]
    public function it_returns_em_dash_for_empty(): void
    {
        $this->assertSame('—', format_ist(null));
        $this->assertSame('—', format_ist(''));
    }
}
