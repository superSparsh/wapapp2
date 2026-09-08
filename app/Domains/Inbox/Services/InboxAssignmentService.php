<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Enums\RecordStatus;
use App\Models\Conversation;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InboxAssignmentService
{
    /**
     * @return Collection<int, array{key: string, label: string, type: string}>
     */
    public function assignableAgents(): Collection
    {
        $agents = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'first_name', 'email'])
            ->map(fn (User $user): array => [
                'key' => 'user:'.$user->uuid,
                'label' => trim((string) ($user->first_name ?: $user->name ?: $user->email)),
                'type' => 'user',
            ]);

        $members = TeamMember::query()
            ->where('status', RecordStatus::Active)
            ->orderBy('first_name')
            ->get(['id', 'uuid', 'first_name', 'last_name', 'email'])
            ->map(fn (TeamMember $member): array => [
                'key' => 'member:'.$member->uuid,
                'label' => trim($member->first_name.' '.$member->last_name) ?: $member->email,
                'type' => 'member',
            ]);

        return $agents->concat($members)->values();
    }

    public function assign(Conversation $conversation, ?string $assigneeKey): Conversation
    {
        return DB::transaction(function () use ($conversation, $assigneeKey): Conversation {
            if ($assigneeKey === null || $assigneeKey === '' || $assigneeKey === 'unassigned') {
                $conversation->forceFill([
                    'assigned_user_id' => null,
                    'assigned_team_member_id' => null,
                ])->save();

                app(InboxBroadcastService::class)->threadUpdated($conversation->refresh());

                return $conversation->refresh();
            }

            if (! str_contains($assigneeKey, ':')) {
                abort(422, 'Invalid assignee.');
            }

            [$type, $uuid] = explode(':', $assigneeKey, 2);

            if ($type === 'user') {
                $user = User::query()->where('uuid', $uuid)->firstOrFail();
                $conversation->forceFill([
                    'assigned_user_id' => $user->id,
                    'assigned_team_member_id' => null,
                ])->save();

                app(InboxBroadcastService::class)->threadUpdated($conversation->refresh());

                return $conversation->refresh();
            }

            if ($type === 'member') {
                $member = TeamMember::query()->where('uuid', $uuid)->firstOrFail();
                $conversation->forceFill([
                    'assigned_user_id' => null,
                    'assigned_team_member_id' => $member->id,
                ])->save();

                app(InboxBroadcastService::class)->threadUpdated($conversation->refresh());

                return $conversation->refresh();
            }

            abort(422, 'Invalid assignee type.');
        });
    }

    public function resolveAssigneeFilter(?string $assigneeKey): ?array
    {
        if ($assigneeKey === null || $assigneeKey === '' || $assigneeKey === 'all') {
            return null;
        }

        if ($assigneeKey === 'unassigned') {
            return ['unassigned' => true];
        }

        if (! str_contains($assigneeKey, ':')) {
            return null;
        }

        [$type, $uuid] = explode(':', $assigneeKey, 2);

        if ($type === 'user') {
            $userId = User::query()->where('uuid', $uuid)->value('id');

            return $userId ? ['user_id' => (int) $userId] : null;
        }

        if ($type === 'member') {
            $memberId = TeamMember::query()->where('uuid', $uuid)->value('id');

            return $memberId ? ['team_member_id' => (int) $memberId] : null;
        }

        return null;
    }
}
