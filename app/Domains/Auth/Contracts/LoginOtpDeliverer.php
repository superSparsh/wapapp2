<?php

declare(strict_types=1);

namespace App\Domains\Auth\Contracts;

use App\Models\TenantUserAccess;

interface LoginOtpDeliverer
{
    public function send(string $phone, string $otp, TenantUserAccess $access): void;
}
