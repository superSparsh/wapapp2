<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminSession;
use App\Domains\Auth\Services\TenantResolver;
use App\Domains\Auth\Support\AuthSession;
use App\Enums\TenantUserAccountType;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AdminImpersonationService
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
    ) {}

    public function loginAsTenant(Admin $admin, Tenant $tenant): void
    {
        $access = TenantUserAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('account_type', TenantUserAccountType::Owner)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($access === null) {
            $access = TenantUserAccess::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        if ($access === null) {
            throw new RuntimeException('No active login access found for this customer.');
        }

        tenancy()->initialize($tenant);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $access->email)])
            ->first()
            ?? User::query()->where('role', UserRole::Owner)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($user === null) {
            throw new RuntimeException('No owner user found inside the customer tenant.');
        }

        if (! (bool) $user->is_active) {
            throw new RuntimeException('Customer owner account is inactive.');
        }

        session([
            AdminSession::IMPERSONATION => [
                'admin_id' => (int) $admin->id,
                'tenant_id' => (string) $tenant->id,
                'tenant_name' => (string) $tenant->name,
                'admin_name' => (string) $admin->name,
                'admin_email' => strtolower((string) $admin->email),
            ],
        ]);

        Auth::guard('admin')->logout();

        // Drop any leftover customer auth keys before establishing the new login so
        // AuthenticateSession cannot reject a stale password_hash_web on the next hop.
        $this->tenantResolver->forgetTenantScopedAuthSession();

        $guard = Auth::guard('web');
        $guard->login($user);

        $this->tenantResolver->storeInSession((string) $tenant->id, 'web');
        session([AuthSession::TWO_FACTOR_VERIFIED => true]);

        $passwordHash = $user->getAuthPassword();
        if (is_string($passwordHash) && $passwordHash !== '') {
            session()->put(
                'password_hash_web',
                $guard->hashPasswordForCookie($passwordHash),
            );
        }
    }

    public function stop(): void
    {
        $payload = AdminSession::impersonation();
        if ($payload === null) {
            throw new RuntimeException('No admin impersonation session is active.');
        }

        Auth::guard('web')->logout();
        Auth::guard('team')->logout();

        $this->tenantResolver->forgetTenantScopedAuthSession();

        session()->forget(AdminSession::IMPERSONATION);

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $admin = Admin::query()->find($payload['admin_id']);
        if ($admin === null || ! $admin->is_active) {
            throw new RuntimeException('Original admin account is missing or disabled.');
        }

        Auth::guard('admin')->login($admin);
    }
}
