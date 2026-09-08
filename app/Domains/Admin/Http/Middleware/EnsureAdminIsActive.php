<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin === null) {
            return redirect()->route('admin.login');
        }

        if (! (bool) ($admin->is_active ?? false)) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This admin account is disabled.']);
        }

        return $next($request);
    }
}
