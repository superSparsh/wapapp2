<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Domains\Team\Support\TeamPermissions;
use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\ManagerMemberAssignment;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeamMemberService
{
    public function __construct(
        private readonly TeamAccessSyncService $accessSyncService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $owner, array $data): TeamMember
    {
        $member = TeamMember::query()->create([
            'parent_user_id' => $owner->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower((string) $data['email']),
            'phone' => PhoneNormalizer::normalize((string) $data['phone']),
            'password' => Hash::make((string) $data['password']),
            'role' => TeamMemberRole::from((string) $data['role']),
            'status' => RecordStatus::Active,
            'permissions' => TeamPermissions::defaults(),
            'assigned_whatsapp_line_ids' => $this->resolveWhatsappLineIds($data['whatsapp_line_ids'] ?? null),
        ]);

        $this->accessSyncService->sync($member);

        return $member;
    }

    /** @param array<string, mixed> $data */
    public function update(TeamMember $member, array $data): TeamMember
    {
        $payload = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => PhoneNormalizer::normalize((string) $data['phone']),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make((string) $data['password']);
        }

        if (array_key_exists('whatsapp_line_ids', $data)) {
            $member->assigned_whatsapp_line_ids = $this->resolveWhatsappLineIds($data['whatsapp_line_ids']);
        }

        $member->fill($payload)->save();
        $this->accessSyncService->sync($member, $member->isActive());

        return $member->refresh();
    }

    public function delete(TeamMember $member): void
    {
        DB::transaction(function () use ($member): void {
            ManagerMemberAssignment::query()
                ->where('manager_id', $member->id)
                ->orWhere('member_id', $member->id)
                ->delete();

            $this->accessSyncService->remove($member);
            $member->delete();
        });
    }

    public function toggleStatus(TeamMember $member): TeamMember
    {
        $member->status = $member->isActive() ? RecordStatus::Inactive : RecordStatus::Active;
        $member->save();

        $this->accessSyncService->sync($member, $member->isActive());

        return $member->refresh();
    }

    /** @param array<string, bool> $permissions */
    public function updatePermissions(TeamMember $member, array $permissions): TeamMember
    {
        $member->permissions = TeamPermissions::fromInput($permissions);
        $member->save();

        return $member->refresh();
    }

    /** @param array<int, int> $lineIds */
    public function syncWhatsappLines(TeamMember $member, array $lineIds): TeamMember
    {
        $validIds = \App\Models\WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->whereIn('id', $lineIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $member->assigned_whatsapp_line_ids = $validIds !== [] ? $validIds : $this->defaultWhatsappLineIds();
        $member->save();

        return $member->refresh();
    }

    public function createForManager(TeamMember $manager, array $data): TeamMember
    {
        abort_unless($manager->isManager(), 403);

        $owner = User::query()->findOrFail($manager->parent_user_id);
        $member = $this->create($owner, array_merge($data, ['role' => TeamMemberRole::Member->value]));

        ManagerMemberAssignment::query()->create([
            'parent_user_id' => $owner->id,
            'manager_id' => $manager->id,
            'member_id' => $member->id,
        ]);

        return $member;
    }

    /** @param array<int, string> $memberUuids */
    public function syncManagerAssignments(User $owner, TeamMember $manager, array $memberUuids): void
    {
        abort_unless($manager->isManager(), 422, 'Assignments are only available for managers.');

        DB::transaction(function () use ($owner, $manager, $memberUuids): void {
            ManagerMemberAssignment::query()
                ->where('parent_user_id', $owner->id)
                ->where('manager_id', $manager->id)
                ->delete();

            if ($memberUuids === []) {
                return;
            }

            $memberIds = TeamMember::query()
                ->where('parent_user_id', $owner->id)
                ->where('role', TeamMemberRole::Member)
                ->whereIn('uuid', $memberUuids)
                ->pluck('id');

            foreach ($memberIds as $memberId) {
                ManagerMemberAssignment::query()->create([
                    'parent_user_id' => $owner->id,
                    'manager_id' => $manager->id,
                    'member_id' => $memberId,
                ]);
            }
        });
    }

    /** @param array<int, mixed>|null $lineIds */
    private function resolveWhatsappLineIds(?array $lineIds): ?array
    {
        if ($lineIds === null || $lineIds === []) {
            return $this->defaultWhatsappLineIds();
        }

        $uuids = array_values(array_filter(array_map(
            static fn ($id): string => trim((string) $id),
            $lineIds,
        )));

        $validIds = \App\Models\WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->whereIn('uuid', $uuids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $validIds !== [] ? $validIds : $this->defaultWhatsappLineIds();
    }

    /** @return array<int, int>|null */
    private function defaultWhatsappLineIds(): ?array
    {
        $lineId = \App\Models\WhatsappLine::query()
            ->where('status', RecordStatus::Active)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id');

        return $lineId ? [(int) $lineId] : null;
    }
}
