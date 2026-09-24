<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Middleware;

use App\Domains\Integration\Support\LineContextGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the session is locked to one WhatsApp number, block account-security
 * and admin routes so operators stay in the ops workspace for that line.
 */
class RestrictLineContextRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! LineContextGate::isActive()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (LineContextGate::allowsRoute($routeName)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => 'This action is only available on the main account. Exit number mode first.',
            ], 403);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'That page is only available on the main account. Exit number mode to manage security, billing, or integrations.');
    }
}
