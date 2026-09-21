<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Support;

use Carbon\Carbon;
use Throwable;

/**
 * Legacy ChatbotFlowService::isWithinBusinessHours / shouldSendOfflineHoursMessage parity.
 * Used by welcome + template nodes when enableOfflineHours is on.
 */
final class OfflineHoursEvaluator
{
    /**
     * @param  array<string, mixed>  $nodeData
     */
    public static function shouldSendOfflineMessage(array $nodeData): bool
    {
        if (! self::flagEnabled($nodeData)) {
            return false;
        }

        $offline = trim((string) ($nodeData['offlineMessage'] ?? ''));
        if ($offline === '') {
            return false;
        }

        return ! self::isWithinBusinessHours($nodeData);
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    public static function resolveSessionText(array $nodeData, string $onlineText): string
    {
        if (! self::shouldSendOfflineMessage($nodeData)) {
            return $onlineText;
        }

        $offline = trim((string) ($nodeData['offlineMessage'] ?? ''));

        return $offline !== '' ? $offline : $onlineText;
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    public static function isWithinBusinessHours(array $nodeData): bool
    {
        if (! self::flagEnabled($nodeData)) {
            return true;
        }

        $timezone = trim((string) ($nodeData['timezone'] ?? 'Asia/Kolkata'));
        if ($timezone === '') {
            $timezone = 'Asia/Kolkata';
        }

        try {
            $now = Carbon::now($timezone);
        } catch (Throwable) {
            $now = Carbon::now('Asia/Kolkata');
        }

        $fromMinutes = self::parseHourMinute((string) ($nodeData['onlineFrom'] ?? '09:00'));
        $untilMinutes = self::parseHourMinute((string) ($nodeData['onlineUntil'] ?? '21:00'));

        if ($fromMinutes === null || $untilMinutes === null) {
            return true;
        }

        $nowMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');

        if ($fromMinutes === $untilMinutes) {
            return true;
        }

        if ($fromMinutes < $untilMinutes) {
            // Inclusive closing minute — "until 21:00" stays open at 21:00.
            return $nowMinutes >= $fromMinutes && $nowMinutes <= $untilMinutes;
        }

        // Overnight window (e.g. 22:00–06:00)
        return $nowMinutes >= $fromMinutes || $nowMinutes <= $untilMinutes;
    }

    /**
     * @param  array<string, mixed>  $nodeData
     */
    private static function flagEnabled(array $nodeData): bool
    {
        return self::truthy($nodeData['enableOfflineHours'] ?? false);
    }

    public static function truthy(mixed $flag): bool
    {
        if ($flag === true || $flag === 1 || $flag === 1.0) {
            return true;
        }

        if (is_string($flag)) {
            return in_array(strtolower(trim($flag)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private static function parseHourMinute(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Accept HH:mm, H:mm, and HH:mm:ss from TimePicker / legacy builders.
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];

            if ($hour > 23 || $minute > 59) {
                return null;
            }

            return ($hour * 60) + $minute;
        }

        // Soft-parse "9:00 AM" / "9am" style values.
        try {
            $parsed = Carbon::parse($value, 'UTC');

            return ((int) $parsed->format('H') * 60) + (int) $parsed->format('i');
        } catch (Throwable) {
            return null;
        }
    }
}
