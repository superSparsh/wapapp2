<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services\OtpDelivery;

use App\Domains\Auth\Contracts\LoginOtpDeliverer;
use App\Domains\Auth\Exceptions\OtpDeliveryException;
use App\Domains\Auth\Services\TenantResolver;
use App\Models\TeamMember;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Notification;

class MailLoginOtpDeliverer implements LoginOtpDeliverer
{
    public function __construct(
        private readonly TenantResolver $tenantResolver,
    ) {}

    public function send(string $phone, string $otp, TenantUserAccess $access): void
    {
        try {
            $this->tenantResolver->initializeForEmail($access->email);

            $notifiable = $access->account_type->value === 'team'
                ? TeamMember::query()->where('email', $access->email)->first()
                : User::query()->where('email', $access->email)->first();

            if ($notifiable === null) {
                throw OtpDeliveryException::make();
            }

            Notification::send($notifiable, new LoginOtpNotification($otp, $phone));
        } finally {
            if (tenant()) {
                tenancy()->end();
            }
        }
    }
}
