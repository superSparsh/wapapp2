<?php

declare(strict_types=1);

namespace Tests\Unit\ThirdParty;

use App\Domains\ThirdParty\Services\GoogleCalendarApiService;
use Carbon\Carbon;
use Tests\TestCase;

class GoogleCalendarWatchExpirationTest extends TestCase
{
    public function test_parse_watch_expiration_accepts_google_millisecond_timestamp(): void
    {
        $api = new GoogleCalendarApiService();
        $ms = 1794235512000;

        $parsed = $api->parseWatchExpiration($ms);

        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame($ms, $parsed->getTimestampMs());
    }

    public function test_parse_watch_expiration_accepts_millisecond_string(): void
    {
        $api = new GoogleCalendarApiService();

        $parsed = $api->parseWatchExpiration('1794235512000');

        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame(1794235512000, $parsed->getTimestampMs());
    }

    public function test_parse_watch_expiration_accepts_second_timestamp(): void
    {
        $api = new GoogleCalendarApiService();
        $seconds = 1794235512;

        $parsed = $api->parseWatchExpiration($seconds);

        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame($seconds, $parsed->getTimestamp());
    }

    public function test_parse_watch_expiration_returns_null_for_empty(): void
    {
        $api = new GoogleCalendarApiService();

        $this->assertNull($api->parseWatchExpiration(null));
        $this->assertNull($api->parseWatchExpiration(''));
    }
};
