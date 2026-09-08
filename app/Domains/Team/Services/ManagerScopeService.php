<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Models\ManagerMemberAssignment;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Cache;

class ManagerScopeService
{
    /** @return array<int, int> */
    public function assignedMemberIds(TeamMember $manager): array
    {
        return Cache::store('array')->remember(
            'manager_scope_'.$manager->id,
            60,
            fn (): array => ManagerMemberAssignment::query()
                ->where('manager_id', $manager->id)
                ->pluck('member_id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
    }

    public function canManage(TeamMember $manager, TeamMember $member): bool
    {
        abort_unless($manager->isManager(), 403);

        if ((int) $member->parent_user_id !== (int) $manager->parent_user_id) {
            return false;
        }

        return in_array((int) $member->id, $this->assignedMemberIds($manager), true);
    }

    public function authorizeManage(TeamMember $manager, TeamMember $member): void
    {
        abort_unless($this->canManage($manager, $member), 404);
    }
}
