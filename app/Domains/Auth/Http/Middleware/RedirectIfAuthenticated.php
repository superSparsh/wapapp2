<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = $guards === [] ? ['web', 'team'] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                if ($guard === 'admin') {
                    return redirect()->route('admin.dashboard');
                }

                $user = Auth::guard('web')->user();

                if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
                    if ($request->routeIs('signup.email', 'signup.email.resend', 'signup.email.verify')) {
                        return $next($request);
                    }

                    return redirect()->route('signup.email');
                }

                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
