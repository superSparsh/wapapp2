<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminSession;
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
            ->where('email', $access->email)
            ->first()
            ?? User::query()->where('role', UserRole::Owner)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($user === null) {
            throw new RuntimeException('No owner user found inside the customer tenant.');
        }

        session([
            AdminSession::IMPERSONATION => [
                'admin_id' => (int) $admin->id,
                'tenant_id' => (string) $tenant->id,
                'admin_name' => (string) $admin->name,
            ],
        ]);

        Auth::guard('admin')->logout();
        Auth::guard('web')->login($user);
        session()->put('auth', [
            'tenant_id' => (string) $tenant->id,
            'guard' => 'web',
        ]);
        session([AuthSession::TWO_FACTOR_VERIFIED => true]);
    }

    public function stop(): void
    {
        $payload = AdminSession::impersonation();
        if ($payload === null) {
            throw new RuntimeException('No admin impersonation session is active.');
        }

        Auth::guard('web')->logout();
        Auth::guard('team')->logout();

        session()->forget([
            'auth',
            AuthSession::TENANT_ID,
            AuthSession::GUARD,
            AuthSession::TWO_FACTOR_VERIFIED,
            AdminSession::IMPERSONATION,
        ]);

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
