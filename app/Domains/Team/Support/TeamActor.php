<?php

declare(strict_types=1);

namespace App\Domains\Team\Support;

use App\Models\TeamMember;
use App\Models\User;

final class TeamActor
{
    public static function user(): ?User
    {
        $user = auth('web')->user();

        return $user instanceof User ? $user : null;
    }

    public static function teamMember(): ?TeamMember
    {
        $member = auth('team')->user();

        return $member instanceof TeamMember ? $member : null;
    }

    public static function isOwner(): bool
    {
        return self::user() !== null;
    }

    public static function isTeamMember(): bool
    {
        return self::teamMember() !== null;
    }

    public static function isManager(): bool
    {
        return self::teamMember()?->isManager() ?? false;
    }

    public static function actor(): User|TeamMember|null
    {
        return self::user() ?? self::teamMember();
    }
}
