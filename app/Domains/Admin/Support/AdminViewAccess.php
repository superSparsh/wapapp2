<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

use App\Models\Admin;
use App\Support\CurrentAccount;

final class AdminViewAccess
{
    /**
     * Legacy parity: show "Admin View" in tenant UI when the signed-in
     * account email matches an active platform admin.
     */
    public static function canAccess(): bool
    {
        $email = strtolower(trim((string) CurrentAccount::email()));
        if ($email === '') {
            return false;
        }

        return Admin::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->exists();
    }
}
