<?php

declare(strict_types=1);

namespace App\Enums;

enum TeamMemberRole: string
{
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Member => 'Team member',
        };
    }
}
