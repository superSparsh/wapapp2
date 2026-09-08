<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\RecordStatus;
use App\Enums\TeamMemberRole;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class TeamMemberImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'team';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('team_members')) {
            return;
        }

        $ownerId = $ids->getInt('user', $customer->id)
            ?? User::query()->where('role', 'owner')->value('id');

        if ($ownerId === null) {
            $report->warn('Team import skipped: owner user missing.');

            return;
        }

        $rows = $this->legacy->db()->table('team_members')
            ->where('parent_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row->email ?? '')));
            if ($email === '') {
                $report->bump($this->key(), 'skipped');

                continue;
            }

            $phone = PhoneNormalizer::normalize((string) ($row->phone_number ?? ''));
            $legacyId = (int) $row->id;

            if ($dryRun) {
                $report->bump($this->key(), TeamMember::query()->where('email', $email)->exists() ? 'updated' : 'created');

                continue;
            }

            $member = TeamMember::query()->where('email', $email)->first();
            $password = filled($row->password) && str_starts_with((string) $row->password, '$2')
                ? (string) $row->password
                : Hash::make(Str::password(24));

            $lineIds = $this->mapAssignedLines($row->assigned_whatsapp_numbers ?? null, $ids);

            $attributes = [
                'parent_user_id' => $ownerId,
                'first_name' => $row->first_name,
                'last_name' => $row->last_name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'role' => $this->mapRole($row->role ?? null),
                'status' => $this->mapStatus($row->status ?? null),
                'auto_assign_chats' => (bool) ($row->auto_assign_chats ?? false),
                'assigned_whatsapp_line_ids' => $lineIds,
                'permissions' => $this->decodeJson($row->permissions ?? null),
            ];

            if ($member !== null) {
                $member->forceFill($attributes)->save();
                $report->bump($this->key(), 'updated');
            } else {
                $member = TeamMember::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('team_member', $legacyId, $member->id);
        }
    }

    private function mapRole(mixed $role): TeamMemberRole
    {
        $value = strtolower((string) $role);

        return str_contains($value, 'manager')
            ? TeamMemberRole::Manager
            : TeamMemberRole::Member;
    }

    private function mapStatus(mixed $status): RecordStatus
    {
        $value = strtolower((string) $status);

        return in_array($value, ['0', 'inactive', 'disabled'], true)
            ? RecordStatus::Inactive
            : RecordStatus::Active;
    }

    /**
     * @return list<int>
     */
    private function mapAssignedLines(mixed $raw, MigrationIdMap $ids): array
    {
        $decoded = $this->decodeJson($raw);
        if (! is_array($decoded)) {
            return [];
        }

        $mapped = [];
        foreach ($decoded as $legacyLineId) {
            $newId = $ids->getInt('line', (int) $legacyLineId);
            if ($newId !== null) {
                $mapped[] = $newId;
            }
        }

        return array_values(array_unique($mapped));
    }

    private function decodeJson(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
