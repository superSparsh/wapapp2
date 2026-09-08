<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminViewAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnterAdminViewController extends Controller
{
    /**
     * Bridge from tenant app → platform admin (legacy "Admin View" link).
     * Legacy used a GET link to Admin\HomeController when @can('admin_access').
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $admin = AdminViewAccess::matchingAdmin();

        if ($admin === null) {
            abort_unless(AdminViewAccess::emailIsAllowlisted(), 403);

            return redirect()
                ->route('admin.login')
                ->with('status', 'Sign in with your admin account to open Admin View.');
        }

        Auth::guard('admin')->login($admin, true);
        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('admin.dashboard');
    }
}
