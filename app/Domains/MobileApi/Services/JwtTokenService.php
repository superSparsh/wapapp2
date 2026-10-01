<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Contracts\Auth\Authenticatable;
use stdClass;

final class JwtTokenService
{
    public function issuePair(Authenticatable $user, string $tenantId, string $guard): array
    {
        $accessTtl = (int) config('mobile-api.access_ttl_seconds', 3600);
        $refreshTtl = (int) config('mobile-api.refresh_ttl_seconds', 2_592_000);

        return [
            'access_token' => $this->encode($user, $tenantId, $guard, 'access', $accessTtl),
            'refresh_token' => $this->encode($user, $tenantId, $guard, 'refresh', $refreshTtl),
            'token_type' => 'Bearer',
            'expires_in' => $accessTtl,
        ];
    }

    public function encode(
        Authenticatable $user,
        string $tenantId,
        string $guard,
        string $type,
        int $ttlSeconds,
    ): string {
        $now = time();
        $issuer = (string) config('mobile-api.issuer', config('app.url'));

        $payload = [
            'iss' => $issuer,
            'aud' => $issuer,
            'iat' => $now,
            'exp' => $now + max(60, $ttlSeconds),
            'user_id' => (int) $user->getAuthIdentifier(),
            'email' => (string) ($user->email ?? ''),
            'tenant_id' => $tenantId,
            'guard' => $guard,
            'type' => $type,
        ];

        return JWT::encode($payload, $this->secret(), 'HS256');
    }

    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }

    private function secret(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            return $decoded !== false && $decoded !== '' ? $decoded : $key;
        }

        return $key;
    }
}
