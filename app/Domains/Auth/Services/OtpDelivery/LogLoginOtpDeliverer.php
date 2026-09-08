<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\OtpDelivery;

use App\Domains\Auth\Contracts\LoginOtpDeliverer;
use App\Domains\Auth\Exceptions\OtpDeliveryException;
use App\Models\TenantUserAccess;
use Illuminate\Support\Facades\Log;

class LogLoginOtpDeliverer implements LoginOtpDeliverer
{
    public function send(string $phone, string $otp, TenantUserAccess $access): void
    {
        Log::info('Login OTP generated', [
            'phone' => $phone,
            'otp' => $otp,
            'tenant_id' => $access->tenant_id,
        ]);
    }
}
