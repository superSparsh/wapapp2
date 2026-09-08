<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

use App\Models\Admin;
use App\Support\CurrentAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AdminViewAccess
{
    /**
     * Admin View ONLY for:
     * 1) email present on an active row in central `admins` table, OR
     * 2) email listed in ADMIN_VIEW_EMAILS (.env)
     *
     * No other customer sees this option.
     */
    public static function canAccess(): bool
    {
        $email = self::currentEmail();
        if ($email === '') {
            return false;
        }

        if (self::emailIsAllowlisted($email)) {
            return true;
        }

        return self::findActiveAdminByEmail() !== null;
    }

    /**
     * Resolve the Admin for SSO login. Allowlisted emails get an Admin row
     * auto-provisioned so click → dashboard with zero login page.
     */
    public static function resolveAdminForSso(): ?Admin
    {
        $email = self::currentEmail();
        if ($email === '') {
            return null;
        }

        $existing = self::findAdminByEmailIncludingTrashed($email);

        if ($existing instanceof Admin) {
            return self::ensureAdminReadyForSso($existing, $email);
        }

        if (! self::emailIsAllowlisted($email)) {
            return null;
        }

        return self::onCentral(static function () use ($email): Admin {
            return Admin::query()->create([
                'name' => CurrentAccount::displayName() ?: 'Admin',
                'email' => $email,
                'password' => Hash::make(Str::password(32)),
                'is_active' => true,
            ]);
        });
    }

    /** @deprecated Use resolveAdminForSso() */
    public static function matchingAdmin(): ?Admin
    {
        return self::resolveAdminForSso();
    }

    public static function emailIsAllowlisted(?string $email = null): bool
    {
        $email = strtolower(trim((string) ($email ?? self::currentEmail())));
        if ($email === '') {
            return false;
        }

        $allowlist = array_map(
            static fn (string $value): string => strtolower(trim($value)),
            config('admin.view_emails', []),
        );

        return in_array($email, $allowlist, true);
    }

    private static function findActiveAdminByEmail(): ?Admin
    {
        $email = self::currentEmail();
        if ($email === '') {
            return null;
        }

        return self::onCentral(static function () use ($email): ?Admin {
            return Admin::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('is_active', true)
                ->first();
        });
    }

    private static function findAdminByEmailIncludingTrashed(string $email): ?Admin
    {
        return self::onCentral(static function () use ($email): ?Admin {
            return Admin::query()
                ->withTrashed()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        });
    }

    private static function ensureAdminReadyForSso(Admin $admin, string $email): ?Admin
    {
        return self::onCentral(static function () use ($admin, $email): ?Admin {
            if ($admin->trashed()) {
                if (! self::emailIsAllowlisted($email)) {
                    return null;
                }
                $admin->restore();
            }

            if (! $admin->is_active) {
                if (! self::emailIsAllowlisted($email)) {
                    return null;
                }
                $admin->forceFill(['is_active' => true])->save();
            }

            return $admin->fresh();
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private static function onCentral(callable $callback): mixed
    {
        try {
            if (function_exists('tenancy') && tenancy()->initialized) {
                return tenancy()->central($callback);
            }

            return $callback();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function currentEmail(): string
    {
        return strtolower(trim((string) CurrentAccount::email()));
    }
}
