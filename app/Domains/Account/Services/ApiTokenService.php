<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Support\Str;

class ApiTokenService
{
    public function current(User $user): ?string
    {
        return $user->api_token;
    }

    public function ensure(User $user): string
    {
        if (filled($user->api_token)) {
            $token = (string) $user->api_token;
            $this->publishToken($user, $token);

            return $token;
        }

        return $this->renew($user);
    }

    public function renew(User $user): string
    {
        $token = Str::random(60);

        $user->forceFill(['api_token' => $token])->save();
        $this->publishToken($user, $token);

        app(ActivityLogService::class)->log('security.api_token.renewed');

        return $token;
    }

    private function publishToken(User $user, string $token): void
    {
        $tenantId = tenant('id');
        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        tenancy()->central(function () use ($user, $token, $tenantId): void {
            TenantUserAccess::query()
                ->where('tenant_id', $tenantId)
                ->where('email', strtolower((string) $user->email))
                ->update(['api_token' => $token]);
        });
    }
}
