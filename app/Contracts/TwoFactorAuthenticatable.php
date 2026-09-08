<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface TwoFactorAuthenticatable extends Authenticatable
{
    public function hasTwoFactorEnabled(): bool;

    public function twoFactorSecret(): ?string;

    public function setTwoFactorSecret(string $secret): void;

    /** @param  array<int, string>  $codes */
    public function setRecoveryCodes(array $codes): void;

    public function verifyTwoFactorCode(string $code): bool;

    public function consumeRecoveryCode(string $code): bool;
}
