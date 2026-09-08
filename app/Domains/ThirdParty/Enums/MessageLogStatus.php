<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Enums;

enum MessageLogStatus: string
{
    case Sent   = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Sent   => 'Sent',
            self::Failed => 'Failed',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Sent   => 'bg-green-100 text-green-800',
            self::Failed => 'bg-red-100 text-red-800',
        };
    }
}
