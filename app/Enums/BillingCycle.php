<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function defaultValidityDays(): int
    {
        return match ($this) {
            self::Monthly => 30,
            self::Quarterly => 90,
            self::Yearly => 365,
        };
    }
}
