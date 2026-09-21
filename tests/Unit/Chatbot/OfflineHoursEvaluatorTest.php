<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Support\OfflineHoursEvaluator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfflineHoursEvaluatorTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_disabled_flag_never_sends_offline(): void
    {
        $this->assertFalse(OfflineHoursEvaluator::shouldSendOfflineMessage([
            'enableOfflineHours' => false,
            'offlineMessage' => 'We are closed',
            'onlineFrom' => '09:00',
            'onlineUntil' => '18:00',
        ]));
    }

    public function test_outside_hours_uses_offline_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 22:30:00', 'Asia/Kolkata'));

        $data = [
            'enableOfflineHours' => true,
            'timezone' => 'Asia/Kolkata',
            'onlineFrom' => '09:00',
            'onlineUntil' => '21:00',
            'offlineMessage' => 'We are offline',
        ];

        $this->assertTrue(OfflineHoursEvaluator::shouldSendOfflineMessage($data));
        $this->assertSame('We are offline', OfflineHoursEvaluator::resolveSessionText($data, 'Hello online'));
    }

    public function test_inside_hours_uses_online_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 10:00:00', 'Asia/Kolkata'));

        $data = [
            'enableOfflineHours' => true,
            'timezone' => 'Asia/Kolkata',
            'onlineFrom' => '09:00',
            'onlineUntil' => '21:00',
            'offlineMessage' => 'We are offline',
        ];

        $this->assertFalse(OfflineHoursEvaluator::shouldSendOfflineMessage($data));
        $this->assertSame('Hello online', OfflineHoursEvaluator::resolveSessionText($data, 'Hello online'));
    }

    #[DataProvider('overnightProvider')]
    public function test_overnight_window(string $now, bool $expectedOpen): void
    {
        Carbon::setTestNow(Carbon::parse($now, 'Asia/Kolkata'));

        $open = OfflineHoursEvaluator::isWithinBusinessHours([
            'enableOfflineHours' => true,
            'timezone' => 'Asia/Kolkata',
            'onlineFrom' => '22:00',
            'onlineUntil' => '06:00',
        ]);

        $this->assertSame($expectedOpen, $open);
    }

    public function test_string_false_flag_does_not_enable_offline_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 22:30:00', 'Asia/Kolkata'));

        $this->assertFalse(OfflineHoursEvaluator::shouldSendOfflineMessage([
            'enableOfflineHours' => 'false',
            'timezone' => 'Asia/Kolkata',
            'onlineFrom' => '09:00',
            'onlineUntil' => '21:00',
            'offlineMessage' => 'We are offline',
        ]));
    }

    public function test_hh_mm_ss_and_am_pm_times_parse(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 10:00:00', 'Asia/Kolkata'));

        $this->assertTrue(OfflineHoursEvaluator::isWithinBusinessHours([
            'enableOfflineHours' => true,
            'timezone' => 'Asia/Kolkata',
            'onlineFrom' => '09:00:00',
            'onlineUntil' => '21:00:00',
        ]));

        Carbon::setTestNow(Carbon::parse('2026-09-21 22:00:00', 'Asia/Kolkata'));

        $this->assertFalse(OfflineHoursEvaluator::isWithinBusinessHours([
            'enableOfflineHours' => true,
            'timezone' => 'Asia/Kolkata',
            'onlineFrom' => '9:00 AM',
            'onlineUntil' => '9:00 PM',
        ]));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function overnightProvider(): array
    {
        return [
            'evening open' => ['2026-09-21 23:00:00', true],
            'early morning open' => ['2026-09-21 05:00:00', true],
            'midday closed' => ['2026-09-21 12:00:00', false],
        ];
    }
}
