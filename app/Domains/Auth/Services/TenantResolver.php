<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Support\AuthSession;
use App\Enums\TenantUserAccountType;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TenantResolver
{
    public function initializeForEmail(string $email): TenantUserAccess
    {
        $access = TenantUserAccess::findActiveByEmail($email);

        if ($access === null) {
            throw InvalidCredentialsException::make();
        }

        $tenant = Tenant::query()->find($access->tenant_id);

        if ($tenant === null) {
            throw InvalidCredentialsException::make();
        }

        tenancy()->initialize($tenant);

        return $access;
    }

    public function initializeFromSession(): void
    {
        $tenantId = data_get(session('auth'), 'tenant_id') ?? session(AuthSession::TENANT_ID);

        if (! is_string($tenantId) || $tenantId === '') {
            $this->forgetTenantScopedAuthSession();

            return;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            $this->forgetTenantScopedAuthSession();

            return;
        }

        tenancy()->initialize($tenant);
    }

    /**
     * Drop tenant guard session keys without touching the database.
     * Prevents Auth::check() from querying users on the central connection.
     */
    public function forgetTenantScopedAuthSession(): void
    {
        foreach (array_keys(session()->all()) as $key) {
            if (
                str_starts_with($key, 'login_web_')
                || str_starts_with($key, 'login_team_')
                || str_starts_with($key, 'remember_web_')
                || str_starts_with($key, 'remember_team_')
                || str_starts_with($key, 'password_hash_web')
                || str_starts_with($key, 'password_hash_team')
            ) {
                session()->forget($key);
            }
        }

        session()->forget('auth');

        session()->forget([
            AuthSession::TENANT_ID,
            AuthSession::GUARD,
            AuthSession::TWO_FACTOR_VERIFIED,
        ]);
    }

    public function storeInSession(string $tenantId, string $guard): void
    {
        session()->put('auth', array_merge(session('auth', []), [
            'tenant_id' => $tenantId,
            'guard' => $guard,
        ]));
    }
}
