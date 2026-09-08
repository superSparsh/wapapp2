<?php

declare(strict_types=1);

namespace App\Domains\Auth\DTOs;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class LoginResult
{
    public function __construct(
        public Authenticatable $user,
        public string $guard,
        public string $tenantId,
        public bool $requiresTwoFactor,
    ) {}
}
