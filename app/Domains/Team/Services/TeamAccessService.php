<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Domains\Billing\Services\SubscriptionService;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class TeamAccessService
{
    public function owner(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Only account owners can manage team members.');
        }

        return $user;
    }

    public function assertOwner(): void
    {
        $this->owner();
    }

    public function authorizeMember(TeamMember $member): TeamMember
    {
        $owner = $this->owner();

        abort_unless((int) $member->parent_user_id === (int) $owner->id, 404);

        return $member;
    }

    public function memberLimit(): ?int
    {
        $limit = app(SubscriptionService::class)->currentPlan()?->team_members_limit;

        return $limit !== null ? (int) $limit : null;
    }

    public function remainingSlots(): ?int
    {
        $limit = $this->memberLimit();

        if ($limit === null) {
            return null;
        }

        $count = TeamMember::query()
            ->where('parent_user_id', $this->owner()->id)
            ->count();

        return max(0, $limit - $count);
    }

    public function assertCanCreate(): void
    {
        $remaining = $this->remainingSlots();

        if ($remaining !== null && $remaining <= 0) {
            abort(422, 'Team member limit reached for your current plan.');
        }
    }

    public function assertCanCreateForOwnerId(int $ownerId): void
    {
        $limit = $this->memberLimitForOwnerId($ownerId);

        if ($limit === null) {
            return;
        }

        $count = TeamMember::query()->where('parent_user_id', $ownerId)->count();

        if ($count >= $limit) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'limit' => "Team member limit of {$limit} reached.",
            ]);
        }
    }

    public function usageForOwner(User $owner): array
    {
        $limit = $this->memberLimitForOwnerId($owner->id);
        $used = TeamMember::query()->where('parent_user_id', $owner->id)->count();

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit !== null ? max(0, $limit - $used) : null,
            'is_full' => $limit !== null && $used >= $limit,
        ];
    }

    public function manager(): TeamMember
    {
        $member = auth('team')->user();

        if (! $member instanceof TeamMember || ! $member->isManager()) {
            throw new AuthorizationException('Manager access required.');
        }

        return $member;
    }

    private function memberLimitForOwnerId(int $ownerId): ?int
    {
        unset($ownerId);

        return $this->memberLimit();
    }
}
