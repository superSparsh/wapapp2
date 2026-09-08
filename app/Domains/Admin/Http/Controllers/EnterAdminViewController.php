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
     * SSO bridge: tenant app → platform admin (legacy "Admin View").
     * Always logs into the admin guard directly — never the admin login page.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $admin = AdminViewAccess::resolveAdminForSso();

        if ($admin === null) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Admin View is not available for this account.');
        }

        Auth::guard('admin')->login($admin, true);
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('admin.dashboard');
    }
}
