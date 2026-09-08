<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

final class AdminSession
{
    public const IMPERSONATION = 'admin.impersonation';

    /**
     * @return array{admin_id: int, tenant_id: string, admin_name: string}|null
     */
    public static function impersonation(): ?array
    {
        $payload = session(self::IMPERSONATION);

        return is_array($payload) ? $payload : null;
    }

    public static function isImpersonating(): bool
    {
        return self::impersonation() !== null;
    }
}
