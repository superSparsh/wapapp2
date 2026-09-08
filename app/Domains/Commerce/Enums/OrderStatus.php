<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Enums;

enum OrderStatus: string
{
    case New       = 'new';
    case Confirmed = 'confirmed';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New       => 'New',
            self::Confirmed => 'Confirmed',
            self::Shipped   => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New       => 'blue',
            self::Confirmed => 'purple',
            self::Shipped   => 'orange',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }
}
