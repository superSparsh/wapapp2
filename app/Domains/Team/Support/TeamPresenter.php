<?php

declare(strict_types=1);

namespace App\Domains\Team\Support;

use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\TeamMember;

final class TeamPresenter
{
    public static function name(TeamMember $member): string
    {
        return $member->displayName();
    }

    public static function roleLabel(TeamMemberRole $role): string
    {
        return match ($role) {
            TeamMemberRole::Manager => 'Manager',
            TeamMemberRole::Member => 'Member',
        };
    }

    public static function statusLabel(RecordStatus $status): string
    {
        return match ($status) {
            RecordStatus::Active => 'Active',
            RecordStatus::Inactive => 'Inactive',
            RecordStatus::Suspended => 'Suspended',
        };
    }

    /** @return array<string, mixed> */
    public static function listRow(TeamMember $member): array
    {
        return [
            'uuid' => $member->uuid,
            'name' => self::name($member),
            'phone' => $member->phone,
            'email' => $member->email,
            'assigned_conversations' => (int) ($member->assigned_conversations_count ?? 0),
            'role' => $member->role->value,
            'role_label' => self::roleLabel($member->role),
            'status' => $member->status->value,
            'status_label' => self::statusLabel($member->status),
            'is_active' => $member->isActive(),
            'edit_url' => route('my-team.edit', $member),
            'roles_url' => route('my-team.roles', $member),
            'toggle_url' => route('my-team.status', $member),
            'delete_url' => route('my-team.destroy', $member),
        ];
    }

    /** @return array<string, mixed> */
    public static function managerListRow(TeamMember $member): array
    {
        return array_merge(self::listRow($member), [
            'edit_url' => route('manager.team.edit', $member),
            'roles_url' => route('manager.team.roles', $member),
            'toggle_url' => route('manager.team.status', $member),
            'delete_url' => route('manager.team.destroy', $member),
            'login_as_url' => route('manager.team.login-as', $member),
        ]);
    }
}
