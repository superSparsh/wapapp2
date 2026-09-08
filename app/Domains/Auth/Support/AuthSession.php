<?php

declare(strict_types=1);

namespace App\Domains\Auth\Support;

final class AuthSession
{
    public const TENANT_ID = 'auth.tenant_id';

    public const GUARD = 'auth.guard';

    public const TWO_FACTOR_VERIFIED = 'auth.two_factor_verified';

    public const SIGNUP = 'auth.signup';
}
