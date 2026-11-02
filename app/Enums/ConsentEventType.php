<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsentEventType: string
{
    case Given = 'given';
    case Withdrawn = 'withdrawn';
    case Updated = 'updated';
    case Affirmed = 'affirmed';

    public function label(): string
    {
        return match ($this) {
            self::Given => 'Given',
            self::Withdrawn => 'Withdrawn',
            self::Updated => 'Updated',
            self::Affirmed => 'Affirmed',
        };
    }
}
