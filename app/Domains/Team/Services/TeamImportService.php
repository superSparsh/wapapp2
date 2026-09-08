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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamImportService
{
    public function __construct(
        private readonly TeamAccessService $accessService,
        private readonly TeamAccessSyncService $accessSyncService,
        private readonly ManagerScopeService $scopeService,
    ) {}

    /** @return array{created: int, skipped: int, errors: array<int, string>} */
    public function importForManager(TeamMember $manager, UploadedFile $file): array
    {
        abort_unless($manager->isManager(), 403);

        return $this->importRows(
            ownerId: (int) $manager->parent_user_id,
            manager: $manager,
            rows: $this->parseCsv($file),
            enforceLimit: true,
        );
    }

    /** @return array{created: int, skipped: int, errors: array<int, string>} */
    public function importForOwner(User $owner, UploadedFile $file): array
    {
        return $this->importRows(
            ownerId: $owner->id,
            manager: null,
            rows: $this->parseCsv($file),
            enforceLimit: true,
        );
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    private function importRows(int $ownerId, ?TeamMember $manager, array $rows, bool $enforceLimit): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $line => $row) {
            $lineNumber = $line + 2;

            try {
                if ($enforceLimit) {
                    $this->accessService->assertCanCreateForOwnerId($ownerId);
                }

                $member = $this->createFromRow($ownerId, $row);

                if ($manager !== null) {
                    ManagerMemberAssignment::query()->updateOrCreate(
                        [
                            'parent_user_id' => $ownerId,
                            'member_id' => $member->id,
                        ],
                        ['manager_id' => $manager->id],
                    );
                }

                $created++;
            } catch (ValidationException $exception) {
                $skipped++;
                $errors[$lineNumber] = collect($exception->errors())->flatten()->first() ?? 'Invalid row.';
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    /** @return array<int, array<string, string>> */
    private function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'Unable to read the uploaded file.']);
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'CSV file is empty.']);
        }

        $headers = array_map(fn ($header): string => Str::snake(trim((string) $header)), $headers);
        $required = ['first_name', 'last_name', 'email', 'phone_number', 'password'];
        $missing = array_diff($required, $headers);

        if ($missing !== []) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'Missing required columns: '.implode(', ', $missing),
            ]);
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = trim((string) ($data[$index] ?? ''));
            }

            $rows[] = $row;

            if (count($rows) > (int) config('team.import.max_rows', 500)) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'file' => 'CSV exceeds the maximum of '.config('team.import.max_rows', 500).' rows.',
                ]);
            }
        }

        fclose($handle);

        return $rows;
    }

    /** @param array<string, string> $row */
    private function createFromRow(int $ownerId, array $row): TeamMember
    {
        $email = strtolower($row['email']);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Invalid email on row.']);
        }

        if (TeamMember::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => "Email {$email} already exists."]);
        }

        $phone = PhoneNormalizer::normalize($row['phone_number'] ?: $row['phone'] ?? '');

        if ($phone === '') {
            throw ValidationException::withMessages(['phone' => 'Phone number is required.']);
        }

        if (TeamMember::query()->where('parent_user_id', $ownerId)->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => "Phone {$phone} already exists."]);
        }

        $password = $row['password'] !== '' ? $row['password'] : Str::password(12);

        $member = TeamMember::query()->create([
            'parent_user_id' => $ownerId,
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make($password),
            'role' => TeamMemberRole::Member,
            'status' => RecordStatus::Active,
            'permissions' => $this->parsePermissions($row['permissions'] ?? ''),
            'assigned_whatsapp_line_ids' => $this->defaultWhatsappLineIds(),
        ]);

        $this->accessSyncService->sync($member);

        return $member;
    }

    private function parsePermissions(string $raw): array
    {
        if ($raw === '') {
            return TeamPermissions::defaults();
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return TeamPermissions::fromInput($decoded);
        }

        $input = [];

        foreach (explode(';', $raw) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);

            if ($key === null || $value === null) {
                continue;
            }

            $input[trim($key)] = in_array(strtolower(trim($value)), ['yes', '1', 'true'], true);
        }

        return TeamPermissions::fromInput($input);
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

    public function sampleCsv(): string
    {
        return implode("\n", [
            'first_name,last_name,email,phone_number,password,permissions',
            'Ava,Stone,ava@example.com,9876543210,Secret@123,"template_read=yes;audience_read=yes;campaign_read=yes;inbox_read=no"',
        ]);
    }
}
