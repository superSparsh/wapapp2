<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Support;

final class InboxPhoneMasker
{
    public static function mask(?string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($normalized === '') {
            return '******';
        }

        $visible = strlen($normalized) > 4 ? substr($normalized, -4) : $normalized;
        $maskedPrefix = str_repeat('*', max(strlen($normalized) - strlen($visible), 4));

        return $maskedPrefix.$visible;
    }
}
