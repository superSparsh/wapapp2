<?php

declare(strict_types=1);

namespace App\Enums;

enum TeamMemberRole: string
{
    case Manager = 'manager';
    case Member = 'member';
}
