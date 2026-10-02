<?php

declare(strict_types=1);

namespace App\Domains\Auth\Support;

/**
 * Companion cookie for Laravel's remember-me recaller.
 * Tenant DB context is required to resolve remember tokens; session auth.tenant_id
 * disappears when the session expires, so this long-lived cookie restores it.
 */
final class RememberTenantCookie
{
    public const NAME = 'wapapp_remember_tenant';

    /** Match Laravel SessionGuard default remember duration (minutes). */
    private const MINUTES = 576000;

    public static function queue(string $tenantId, string $guard): void
    {
        cookie()->queue(cookie(
            name: self::NAME,
            value: $tenantId.'|'.$guard,
            minutes: self::MINUTES,
            path: '/',
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: config('session.same_site', 'lax'),
        ));
    }

    public static function forget(): void
    {
        cookie()->queue(cookie()->forget(self::NAME));
    }

    /**
     * @return array{tenant_id: string, guard: string}|null
     */
    public static function read(): ?array
    {
        $raw = request()->cookie(self::NAME);

        if (! is_string($raw) || ! str_contains($raw, '|')) {
            return null;
        }

        [$tenantId, $guard] = array_pad(explode('|', $raw, 2), 2, '');
        $tenantId = trim($tenantId);
        $guard = trim($guard);

        if ($tenantId === '' || ! in_array($guard, ['web', 'team'], true)) {
            return null;
        }

        return [
            'tenant_id' => $tenantId,
            'guard' => $guard,
        ];
    }
}
