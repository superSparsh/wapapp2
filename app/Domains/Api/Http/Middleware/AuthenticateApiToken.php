<?php

declare(strict_types=1);

namespace App\Domains\Api\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('api_token')
            ?? $request->bearerToken()
            ?? $request->header('X-Api-Token');

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = tenancy()->central(
            fn () => User::query()->where('api_token', $token)->where('is_active', true)->first(),
        );

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $access = tenancy()->central(
            fn () => TenantUserAccess::findActiveByEmail((string) $user->email),
        );

        if ($access === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tenant = tenancy()->central(
            fn () => Tenant::query()->find($access->tenant_id),
        );

        if ($tenant !== null) {
            tenancy()->initialize($tenant);
        }

        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
