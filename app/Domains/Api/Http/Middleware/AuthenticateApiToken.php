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

        $access = tenancy()->central(
            fn () => TenantUserAccess::query()
                ->where('api_token', $token)
                ->where('is_active', true)
                ->first(),
        );

        if ($access === null) {
            $access = $this->resolveAccessFromTenantUsers($token);
        }

        if ($access === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $tenant = tenancy()->central(
            fn () => Tenant::query()->find($access->tenant_id),
        );

        if ($tenant === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        tenancy()->initialize($tenant);

        $user = User::query()->where('api_token', $token)->where('is_active', true)->first();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function resolveAccessFromTenantUsers(string $token): ?TenantUserAccess
    {
        $candidates = tenancy()->central(
            fn () => TenantUserAccess::query()
                ->where('is_active', true)
                ->whereNull('api_token')
                ->limit(200)
                ->get(['id', 'email', 'tenant_id']),
        );

        foreach ($candidates as $candidate) {
            $tenant = tenancy()->central(
                fn () => Tenant::query()->find($candidate->tenant_id),
            );

            if ($tenant === null) {
                continue;
            }

            tenancy()->initialize($tenant);

            try {
                $user = User::query()
                    ->where('api_token', $token)
                    ->where('is_active', true)
                    ->where('email', $candidate->email)
                    ->first();

                if (! $user instanceof User) {
                    continue;
                }

                tenancy()->central(function () use ($candidate, $token): void {
                    TenantUserAccess::query()
                        ->whereKey($candidate->id)
                        ->update(['api_token' => $token]);
                });

                return tenancy()->central(
                    fn () => TenantUserAccess::query()->find($candidate->id),
                );
            } finally {
                tenancy()->end();
            }
        }

        return null;
    }
}
