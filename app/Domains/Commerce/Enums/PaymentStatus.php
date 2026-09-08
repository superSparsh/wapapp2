<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid    = 'paid';
    case Failed  = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid    => 'Paid',
            self::Failed  => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'orange',
            self::Paid    => 'green',
            self::Failed  => 'red',
        };
    }
}
