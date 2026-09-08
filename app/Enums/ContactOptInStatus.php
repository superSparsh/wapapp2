<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactOptInStatus: string
{
    case OptedIn = 'opted_in';
    case OptedOut = 'opted_out';
    case Pending = 'pending';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::OptedIn => 'Opted in',
            self::OptedOut => 'Opted out',
            self::Pending => 'Pending',
            self::Unknown => 'Unknown',
        };
    }
}
