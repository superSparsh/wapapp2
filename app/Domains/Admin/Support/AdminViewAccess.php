<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

use App\Models\Admin;
use App\Support\CurrentAccount;

final class AdminViewAccess
{
    /**
     * Legacy parity: show "Admin View" when the signed-in account email
     * matches an active platform admin.
     */
    public static function canAccess(): bool
    {
        return self::matchingAdmin() !== null;
    }

    public static function matchingAdmin(): ?Admin
    {
        $email = strtolower(trim((string) CurrentAccount::email()));
        if ($email === '') {
            return null;
        }

        $resolver = static function () use ($email): ?Admin {
            return Admin::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('is_active', true)
                ->first();
        };

        if (function_exists('tenancy') && tenancy()->initialized) {
            return tenancy()->central($resolver);
        }

        return $resolver();
    }
}
