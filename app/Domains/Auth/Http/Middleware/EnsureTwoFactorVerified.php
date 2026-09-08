<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Support\AuthSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');

        if ($user !== null
            && method_exists($user, 'hasTwoFactorEnabled')
            && $user->hasTwoFactorEnabled()
            && ! session(AuthSession::TWO_FACTOR_VERIFIED)) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
