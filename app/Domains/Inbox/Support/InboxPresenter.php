<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Support;

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

        return \Illuminate\Support\Carbon::instance($at)->diffForHumans(short: true);
    }

    public static function preview(?string $body, int $limit = 80): string
    {
        $body = trim((string) $body);

        if ($body === '') {
            return '';
        }

        return Str::limit($body, $limit);
    }

    public static function encodeCursor(?\DateTimeInterface $at, int $id): ?string
    {
        if ($at === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::instance($at)->getTimestamp().'|'.$id;
    }

  /**
   * @return array{0: ?\Illuminate\Support\Carbon, 1: ?int}
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

        return [\Illuminate\Support\Carbon::createFromTimestamp((int) $timestamp), (int) $id];
    }
}
