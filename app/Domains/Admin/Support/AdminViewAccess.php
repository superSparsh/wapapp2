<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

use App\Models\Admin;
use App\Support\CurrentAccount;
use Illuminate\Support\Facades\Auth;

final class AdminViewAccess
{
    /**
     * Legacy parity (@can('admin_access')):
     * Show "Admin View" when the signed-in account can open the platform admin.
     *
     * Sources of access:
     * - active Admin row with the same email (central DB)
     * - ADMIN_VIEW_EMAILS allowlist
     * - already authenticated on the admin guard
     */
    public static function canAccess(): bool
    {
        if (Auth::guard('admin')->check()) {
            return true;
        }

        return self::matchingAdmin() !== null || self::emailIsAllowlisted();
    }

    public static function matchingAdmin(): ?Admin
    {
        $email = self::currentEmail();
        if ($email === '') {
            return null;
        }

        $resolver = static function () use ($email): ?Admin {
            return Admin::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('is_active', true)
                ->first();
        };

        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                return tenancy()->central($resolver);
            }

            return $resolver();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function emailIsAllowlisted(): bool
    {
        $email = self::currentEmail();
        if ($email === '') {
            return false;
        }

        $allowlist = array_map(
            static fn (string $value): string => strtolower(trim($value)),
            config('admin.view_emails', []),
        );

        return in_array($email, $allowlist, true);
    }

    private static function currentEmail(): string
    {
        return strtolower(trim((string) CurrentAccount::email()));
    }
}
