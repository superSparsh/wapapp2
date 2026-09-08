<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Enums;

enum EventStatus: string
{
    case Active      = 'active';
    case Canceled    = 'canceled';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Active      => 'Active',
            self::Canceled    => 'Canceled',
            self::Rescheduled => 'Rescheduled',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active      => 'bg-green-100 text-green-800',
            self::Canceled    => 'bg-red-100 text-red-800',
            self::Rescheduled => 'bg-yellow-100 text-yellow-800',
        };
    }
}
