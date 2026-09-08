<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateServiceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('service-auth.token');

        if (empty($configuredToken)) {
            return $next($request);
        }

        $headerName = config('service-auth.header_name', 'X-Service-Token');
        $providedToken = $request->header($headerName) ?: $request->bearerToken();

        if (empty($providedToken) || ! hash_equals((string) $configuredToken, (string) $providedToken)) {
            return response()->json([
                'error' => 'Unauthorized service request',
                'message' => 'Invalid or missing service authentication token.',
            ], 401);
        }

        return $next($request);
    }
}
