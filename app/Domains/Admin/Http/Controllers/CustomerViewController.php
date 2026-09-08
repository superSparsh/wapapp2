<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\AdminImpersonationService;
use App\Enums\TenantUserAccountType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * Legacy “Customer View”: leave admin and open the customer app.
 * Prefer a tenant where this admin’s email is an active user; otherwise send them to Customers.
 */
class CustomerViewController extends Controller
{
    public function __invoke(AdminImpersonationService $impersonation): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();
        $email = strtolower((string) $admin->email);

        $access = TenantUserAccess::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN account_type = ? THEN 0 ELSE 1 END", [TenantUserAccountType::Owner->value])
            ->orderBy('id')
            ->first();

        if ($access === null) {
            return redirect()
                ->route('admin.customers.index')
                ->with('status', 'No customer account linked to this admin email. Use Login as on a customer.');
        }

        $tenant = Tenant::query()->find($access->tenant_id);
        if ($tenant === null) {
            return redirect()
                ->route('admin.customers.index')
                ->with('error', 'Linked customer tenant was not found.');
        }

        try {
            $impersonation->loginAsTenant($admin, $tenant);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.customers.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard');
    }
}
