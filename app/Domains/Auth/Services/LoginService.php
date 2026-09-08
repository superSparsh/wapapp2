<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Domains\Auth\DTOs\LoginResult;
use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Support\AuthSession;
use App\Enums\TenantUserAccountType;
use App\Models\TeamMember;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
    ) {}

    public function attempt(string $email, string $password, bool $remember = false): LoginResult
    {
        $access = $this->tenantResolver->initializeForEmail($email);

        $user = $this->resolveAuthenticatable($access, $email, $password);

        return $this->loginAuthenticated($user, $access, $remember);
    }

    public function loginAuthenticated(
        Authenticatable $user,
        TenantUserAccess $access,
        bool $remember = false,
    ): LoginResult {
        $guard = $access->account_type === TenantUserAccountType::Team ? 'team' : 'web';

        if (! $this->isActive($user)) {
            throw AccountInactiveException::make();
        }

        Auth::guard($guard)->login($user, $remember);

        $user->forceFill(['last_login_at' => now()])->save();

        $this->tenantResolver->storeInSession($access->tenant_id, $guard);

        session()->forget(AuthSession::TWO_FACTOR_VERIFIED);

        $requiresTwoFactor = $guard === 'web'
            && $user instanceof User
            && $user->hasTwoFactorEnabled();

        if (! $requiresTwoFactor) {
            session([AuthSession::TWO_FACTOR_VERIFIED => true]);
        }

        return new LoginResult(
            user: $user,
            guard: $guard,
            tenantId: $access->tenant_id,
            requiresTwoFactor: $requiresTwoFactor,
        );
    }

    private function resolveAuthenticatable(
        TenantUserAccess $access,
        string $email,
        string $password,
    ): Authenticatable {
        if ($access->account_type === TenantUserAccountType::Team) {
            $member = TeamMember::query()->where('email', $email)->first();

            if ($member === null || ! Hash::check($password, $member->password)) {
                throw InvalidCredentialsException::make();
            }

            return $member;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw InvalidCredentialsException::make();
        }

        return $user;
    }

    private function isActive(Authenticatable $user): bool
    {
        if ($user instanceof User) {
            return (bool) $user->is_active;
        }

        if ($user instanceof TeamMember) {
            return $user->status->value === 'active';
        }

        return true;
    }
}
