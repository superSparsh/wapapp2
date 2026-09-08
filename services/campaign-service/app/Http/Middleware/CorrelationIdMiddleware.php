<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('service-auth.correlation_id_header', 'X-Correlation-Id');
        $correlationId = $request->header($header) ?: (string) Str::uuid();

        $request->headers->set($header, $correlationId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set($header, $correlationId);

        return $response;
    }
}
