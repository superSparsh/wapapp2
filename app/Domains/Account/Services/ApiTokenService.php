<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

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
            return (string) $user->api_token;
        }

        return $this->renew($user);
    }

    public function renew(User $user): string
    {
        $token = Str::random(60);

        $user->forceFill(['api_token' => $token])->save();

        app(ActivityLogService::class)->log('security.api_token.renewed');

        return $token;
    }
}
