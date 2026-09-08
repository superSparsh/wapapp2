<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateServiceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = (string) config('service-auth.header', 'X-Service-Token');
        $expectedToken = (string) config('service-auth.token', '');

        $providedToken = $request->header($headerName)
            ?? $request->bearerToken()
            ?? $request->query('_service_token');

        if ($expectedToken === '' || ! hash_equals($expectedToken, (string) $providedToken)) {
            return new JsonResponse([
                'error' => 'Unauthorized service request.',
                'code' => 'SERVICE_UNAUTHORIZED',
            ], 401);
        }

        return $next($request);
    }
}
