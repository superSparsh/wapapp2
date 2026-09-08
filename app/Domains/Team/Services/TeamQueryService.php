<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Domains\Team\Support\TeamPresenter;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TeamQueryService
{
    public function paginateForOwner(User $owner, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));

        return $this->baseQuery($owner->id, $search)
            ->withCount('assignedConversations')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TeamMember $member): array => TeamPresenter::listRow($member));
    }

    public function paginateForManager(TeamMember $manager, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $memberIds = app(ManagerScopeService::class)->assignedMemberIds($manager);

        return $this->baseQuery($manager->parent_user_id, $search)
            ->whereIn('id', $memberIds !== [] ? $memberIds : [0])
            ->withCount('assignedConversations')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TeamMember $member): array => TeamPresenter::managerListRow($member));
    }

    /** @return Builder<TeamMember> */
    private function baseQuery(int $ownerId, ?string $search): Builder
    {
        return TeamMember::query()
            ->where('parent_user_id', $ownerId)
            ->when(filled($search), function (Builder $query) use ($search): void {
                $term = '%'.trim((string) $search).'%';

                $query->where(function (Builder $nested) use ($term): void {
                    $nested->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            });
    }

    /** @return array<int, array{uuid: string, label: string}> */
    public function assignableMembers(User $owner, ?TeamMember $exclude = null): array
    {
        return TeamMember::query()
            ->where('parent_user_id', $owner->id)
            ->where('role', 'member')
            ->when($exclude, fn (Builder $query) => $query->whereKeyNot($exclude->id))
            ->orderBy('first_name')
            ->get(['uuid', 'first_name', 'last_name', 'email'])
            ->map(fn (TeamMember $member): array => [
                'uuid' => $member->uuid,
                'label' => $member->displayName(),
            ])
            ->all();
    }

    /** @return array<int, string> */
    public function assignedMemberUuids(TeamMember $manager): array
    {
        return $manager->managedAssignments()
            ->with('member:id,uuid')
            ->get()
            ->pluck('member.uuid')
            ->filter()
            ->values()
            ->all();
    }
}
