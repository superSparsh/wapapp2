<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Middleware;

use App\Domains\MobileApi\Services\JwtTokenService;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateJwt
{
    public function __construct(
        private readonly JwtTokenService $jwt,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);

        if ($token === null) {
            return response()->json([
                'success' => false,
                'message' => 'Access token is required',
            ], 401);
        }

        try {
            $decoded = $this->jwt->decode($token);
        } catch (ExpiredException) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired',
            ], 401);
        } catch (SignatureInvalidException) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token signature',
            ], 401);
        } catch (\Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
            ], 401);
        }

        if (($decoded->type ?? null) !== 'access') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token type',
            ], 401);
        }

        $tenantId = (string) ($decoded->tenant_id ?? '');
        $guard = (string) ($decoded->guard ?? 'web');
        $userId = (int) ($decoded->user_id ?? 0);

        if ($tenantId === '' || $userId < 1 || ! in_array($guard, ['web', 'team'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token',
            ], 401);
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 401);
        }

        tenancy()->initialize($tenant);

        $user = $guard === 'team'
            ? TeamMember::query()->find($userId)
            : User::query()->find($userId);

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 401);
        }

        if ($user instanceof User && ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is not activated',
            ], 403);
        }

        if ($user instanceof TeamMember && $user->status->value !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not activated',
            ], 403);
        }

        Auth::shouldUse($guard);
        Auth::guard($guard)->setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches) === 1) {
            return $matches[1];
        }

        $query = $request->query('access_token');

        return is_string($query) && $query !== '' ? $query : null;
    }
}
