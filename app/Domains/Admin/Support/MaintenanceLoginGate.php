<?php

declare(strict_types=1);

namespace App\Domains\Admin\Support;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Domains\Auth\Services\TenantResolver;
use App\Domains\Auth\Support\AuthSession;
use App\Models\TeamMember;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Blocks normal customers from finishing login while maintenance is on.
 * Platform admin / Admin View emails are allowed through.
 */
final class MaintenanceLoginGate
{
    public static function rejectUnlessCustomerAdmin(
        Authenticatable $user,
        string $guard,
    ): ?RedirectResponse {
        $maintenance = app(MaintenanceModeService::class);
        if (! $maintenance->enabled()) {
            return null;
        }

        $email = strtolower(trim((string) ($user->email ?? '')));
        if (AdminViewAccess::emailHasAdminAccess($email)) {
            return null;
        }

        Auth::guard($guard)->logout();
        app(TenantResolver::class)->forgetTenantScopedAuthSession();

        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        request()->session()->forget([
            AuthSession::GUARD,
            AuthSession::TWO_FACTOR_VERIFIED,
            'password_hash_web',
            'password_hash_team',
        ]);

        $tab = $user instanceof TeamMember ? ['tab' => 'mobile'] : [];

        return redirect()
            ->route('login', $tab)
            ->withErrors([
                'email' => $maintenance->message(),
            ]);
    }
}
