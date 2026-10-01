<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Middleware;

use App\Domains\Api\Http\Middleware\AuthenticateApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts either a mobile JWT access token or a partner static api_token.
 * Used for /api/v1/mobile/device-token so both mobile JWT and legacy API token clients work.
 */
final class AuthenticateJwtOrApiToken
{
    public function __construct(
        private readonly AuthenticateJwt $jwt,
        private readonly AuthenticateApiToken $apiToken,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken()
            ?? $request->input('api_token')
            ?? $request->header('X-Api-Token');

        if (is_string($token) && $this->looksLikeJwt($token)) {
            return $this->jwt->handle($request, $next);
        }

        return $this->apiToken->handle($request, $next);
    }

    private function looksLikeJwt(string $token): bool
    {
        return substr_count($token, '.') === 2;
    }
}
