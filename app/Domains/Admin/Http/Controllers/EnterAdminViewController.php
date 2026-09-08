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
     * Bridge from tenant app → platform admin (legacy "Admin View").
     * Logs into the admin guard for the Admin row matching the current user email.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = AdminViewAccess::matchingAdmin();
        abort_if($admin === null, 403);

        Auth::guard('admin')->login($admin, true);
        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('admin.dashboard');
    }
}
