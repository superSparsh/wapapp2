<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Support;

use App\Models\TeamMember;
use App\Models\User;

final class InboxActor
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

    public static function userId(): ?int
    {
        return self::user()?->id;
    }

    public static function teamMemberId(): ?int
    {
        return self::teamMember()?->id;
    }
}
