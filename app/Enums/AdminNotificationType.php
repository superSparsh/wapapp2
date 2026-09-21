<?php

declare(strict_types=1);

namespace App\Enums;

enum AdminNotificationType: string
{
    case NewCustomer = 'new_customer';
    case PlatformError = 'platform_error';
    case RenewRequest = 'renew_request';
    case RechargeRequest = 'recharge_request';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::NewCustomer => 'New customer',
            self::PlatformError => 'Platform error',
            self::RenewRequest => 'Renew request',
            self::RechargeRequest => 'Recharge request',
            self::System => 'System',
        };
    }
}
