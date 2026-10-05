<?php

declare(strict_types=1);

namespace App\Domains\Api\Services;

use App\Domains\Auth\Services\LoginService;
use App\Domains\Auth\Services\TenantResolver;
use App\Enums\TenantUserAccountType;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One-time web login tokens for partner API (legacy /login-token → /autologin/{token}).
 */
class ApiLoginTokenService
{
    private const CACHE_PREFIX = 'api_login_token:';

    private const TTL_MINUTES = 15;

    public function __construct(
        private readonly LoginService $loginService,
        private readonly TenantResolver $tenantResolver,
    ) {}

    /**
     * @return array{token: string, url: string, expires_in: int}
     */
    public function issue(User $user, Tenant $tenant): array
    {
        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (int) $user->id,
            'email' => strtolower((string) $user->email),
        ], now()->addMinutes(self::TTL_MINUTES));

        return [
            'token' => $token,
            'url' => url('/autologin/'.$token),
            'expires_in' => self::TTL_MINUTES * 60,
        ];
    }

    public function consume(string $token): void
    {
        $payload = Cache::pull(self::CACHE_PREFIX.$token);
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid or expired login token.');
        }

        $tenantId = (string) ($payload['tenant_id'] ?? '');
        $email = strtolower((string) ($payload['email'] ?? ''));
        if ($tenantId === '' || $email === '') {
            throw new RuntimeException('Invalid or expired login token.');
        }

        $tenant = tenancy()->central(fn () => Tenant::query()->find($tenantId));
        if (! $tenant instanceof Tenant) {
            throw new RuntimeException('Account not found for this login token.');
        }

        tenancy()->initialize($tenant);

        $access = tenancy()->central(
            fn () => TenantUserAccess::query()
                ->where('tenant_id', $tenantId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('is_active', true)
                ->first(),
        );

        if (! $access instanceof TenantUserAccess) {
            $access = new TenantUserAccess([
                'email' => $email,
                'tenant_id' => $tenantId,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
            ]);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $user instanceof User) {
            throw new RuntimeException('User not found for this login token.');
        }

        $this->tenantResolver->storeInSession($tenantId, 'web');
        $this->loginService->loginAuthenticated($user, $access, remember: false);
    }
}
