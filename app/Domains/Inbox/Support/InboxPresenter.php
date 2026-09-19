<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class InboxPresenter
{
    public static function initials(?string $name, ?string $phone = null): string
    {
        $name = trim((string) $name);

        if ($name !== '') {
            $parts = preg_split('/\s+/', $name) ?: [];

            if (count($parts) >= 2) {
                return strtoupper(substr($parts[0], 0, 1).substr($parts[1], 0, 1));
            }

            return strtoupper(substr($name, 0, 2));
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits !== '' ? strtoupper(substr($digits, -2)) : '??';
    }

    public static function relativeTime(?\DateTimeInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        return Carbon::instance($at)->diffForHumans(short: true);
    }

    /**
     * Clock time inside a chat bubble (WhatsApp-style).
     */
    public static function clockTime(?\DateTimeInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        return Carbon::instance($at)->timezone(config('app.timezone'))->format('g:i A');
    }

    /**
     * Day key for grouping messages (Y-m-d in app timezone).
     */
    public static function dateKey(?\DateTimeInterface $at): ?string
    {
        if ($at === null) {
            return null;
        }

        return Carbon::instance($at)->timezone(config('app.timezone'))->toDateString();
    }

    /**
     * WhatsApp-style day chip: Today / Yesterday / weekday / full date.
     */
    public static function dateLabel(?\DateTimeInterface $at): string
    {
        if ($at === null) {
            return '';
        }

        $date = Carbon::instance($at)->timezone(config('app.timezone'))->startOfDay();
        $today = now()->timezone(config('app.timezone'))->startOfDay();

        if ($date->equalTo($today)) {
            return 'Today';
        }

        if ($date->equalTo($today->copy()->subDay())) {
            return 'Yesterday';
        }

        if ($date->greaterThan($today->copy()->subDays(6))) {
            return $date->format('l');
        }

        return $date->format('j F Y');
    }

    public static function preview(?string $body, int $limit = 80): string
    {
        $body = trim((string) $body);

        if ($body === '') {
            return '';
        }

        return Str::limit($body, $limit);
    }

    public static function threadTitle(?string $name, ?string $phone): string
    {
        $name = trim((string) $name);

        if ($name !== '') {
            return $name;
        }

        $phone = trim((string) $phone);

        return $phone !== '' ? $phone : 'Unknown';
    }

    public static function threadPhoneSubtitle(?string $name, ?string $phone): ?string
    {
        $name = trim((string) $name);
        $phone = trim((string) $phone);

        if ($phone === '' || $name === '' || $name === $phone) {
            return null;
        }

        return $phone;
    }

    public static function encodeCursor(?\DateTimeInterface $at, int $id): ?string
    {
        if ($at === null) {
            return null;
        }

        return Carbon::instance($at)->getTimestamp().'|'.$id;
    }

    /**
     * @return array{0: ?Carbon, 1: ?int}
     */
    public static function decodeCursor(?string $cursor): array
    {
        if ($cursor === null || $cursor === '') {
            return [null, null];
        }

        if (! str_contains($cursor, '|')) {
            return [null, null];
        }

        [$timestamp, $id] = explode('|', $cursor, 2);

        if (! is_numeric($timestamp) || ! is_numeric($id)) {
            return [null, null];
        }

        return [Carbon::createFromTimestamp((int) $timestamp), (int) $id];
    }
}
