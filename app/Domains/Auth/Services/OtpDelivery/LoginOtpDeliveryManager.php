<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\OtpDelivery;

use App\Domains\Auth\Contracts\LoginOtpDeliverer;
use App\Domains\Auth\Services\OtpDelivery\LogLoginOtpDeliverer;
use App\Domains\Auth\Services\OtpDelivery\MailLoginOtpDeliverer;
use App\Domains\Auth\Services\OtpDelivery\WhatsAppLoginOtpDeliverer;
use App\Models\TenantUserAccess;

class LoginOtpDeliveryManager
{
    public function deliver(string $phone, string $otp, TenantUserAccess $access): void
    {
        $this->driver()->send($phone, $otp, $access);
    }

    private function driver(): LoginOtpDeliverer
    {
        return match (config('login-otp.driver', 'log')) {
            'mail' => app(MailLoginOtpDeliverer::class),
            'whatsapp' => app(WhatsAppLoginOtpDeliverer::class),
            default => app(LogLoginOtpDeliverer::class),
        };
    }
}
