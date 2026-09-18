<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PhoneCountryCatalog;
use Tests\TestCase;

class PhoneCountryCatalogTest extends TestCase
{
    public function test_catalog_matches_legacy_country_dial_codes(): void
    {
        $countries = PhoneCountryCatalog::all();

        $this->assertCount(232, $countries);
        $this->assertSame('+91', PhoneCountryCatalog::defaultDialCode());

        $india = collect($countries)->firstWhere('code', 'IN');
        $this->assertSame(['code' => 'IN', 'name' => 'India', 'd_code' => '+91'], $india);
    }

    public function test_selected_dial_code_accepts_iso_plus_and_digits(): void
    {
        $this->assertSame('+91', PhoneCountryCatalog::selectedDialCode(null));
        $this->assertSame('+91', PhoneCountryCatalog::selectedDialCode('IN'));
        $this->assertSame('+91', PhoneCountryCatalog::selectedDialCode('91'));
        $this->assertSame('+91', PhoneCountryCatalog::selectedDialCode('+91'));
        $this->assertSame('+971', PhoneCountryCatalog::selectedDialCode('AE'));
    }
}
