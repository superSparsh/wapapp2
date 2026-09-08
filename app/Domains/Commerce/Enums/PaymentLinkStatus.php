<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Enums;

enum PaymentLinkStatus: string
{
    case Created   = 'created';
    case Sent      = 'sent';
    case Paid      = 'paid';
    case Failed    = 'failed';
    case Expired   = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Created   => 'Created',
            self::Sent      => 'Sent',
            self::Paid      => 'Paid',
            self::Failed    => 'Failed',
            self::Expired   => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created   => 'blue',
            self::Sent      => 'purple',
            self::Paid      => 'green',
            self::Failed    => 'red',
            self::Expired   => 'orange',
            self::Cancelled => 'gray',
        };
    }
}
