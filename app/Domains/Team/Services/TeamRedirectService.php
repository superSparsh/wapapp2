<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Domains\Team\Support\TeamPermissions;
use App\Enums\TeamMemberRole;
use App\Models\TeamMember;
use Illuminate\Auth\Access\AuthorizationException;

class TeamRedirectService
{
    public function landingUrl(TeamMember $member): string
    {
        if ($member->role === TeamMemberRole::Manager) {
            return route('manager.team.index');
        }

        foreach (config('team.landing_priority', []) as $permission => $route) {
            if (TeamPermissions::isEnabled($member->permissions, (string) $permission)) {
                return route((string) $route);
            }
        }

        throw new AuthorizationException('No module access has been assigned to your account.');
    }
}
