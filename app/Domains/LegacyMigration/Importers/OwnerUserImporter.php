<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\Account\Services\ApiTokenService;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Services\TenantBootstrapper;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\NotificationContactType;
use App\Enums\UserRole;
use App\Models\NotificationContact;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class OwnerUserImporter implements LegacyImporter
{
    public function __construct(
        private readonly TenantBootstrapper $bootstrapper,
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'owner';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! filled($customer->email)) {
            throw new RuntimeException('Legacy customer has no owner email; cannot migrate.');
        }

        if ($dryRun) {
            $report->bump($this->key(), 'skipped');

            return;
        }

        $legacyUser = $this->legacyOwnerUser($customer);
        $existing = User::query()->where('email', $customer->email)->first();
        $phone = PhoneNormalizer::normalize($customer->phone);

        if ($existing !== null) {
            $existing->forceFill([
                'name' => $customer->ownerFullName(),
                'first_name' => $customer->firstName,
                'last_name' => $customer->lastName,
                'phone' => $phone ?? $existing->phone,
                'role' => UserRole::Owner,
                'is_active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ])->save();

            $user = $existing;
            $report->bump($this->key(), 'updated');
        } else {
            $password = $customer->passwordHash;
            if (! filled($password) || ! str_starts_with((string) $password, '$2')) {
                $password = Hash::make(Str::password(32));
                $report->warn('Owner password missing/invalid for '.$customer->email.'; random password set.');
            }

            $user = User::query()->create([
                'name' => $customer->ownerFullName(),
                'first_name' => $customer->firstName,
                'last_name' => $customer->lastName,
                'email' => $customer->email,
                'phone' => $phone,
                'password' => $password,
                'role' => UserRole::Owner,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $report->bump($this->key(), 'created');
        }

        $ids->put('user', $customer->id, $user->id);
        if ($legacyUser !== null) {
            $ids->put('legacy_user', (int) $legacyUser->id, $user->id);
        }

        $this->bootstrapper->ensureOwnerAccess($tenant, $customer->email, $customer->phone);
        $this->importApiToken($user, $legacyUser, $report);
        $this->importNotificationContacts($user, $legacyUser, $ids, $report);
    }

    private function legacyOwnerUser(LegacyCustomerSnapshot $customer): ?object
    {
        if (! $this->legacy->tableExists('users')) {
            return null;
        }

        $query = $this->legacy->db()->table('users')->where('customer_id', $customer->id);
        if (filled($customer->email)) {
            $query->whereRaw('LOWER(email) = ?', [strtolower((string) $customer->email)]);
        }

        return $query->orderBy('id')->first();
    }

    private function importApiToken(User $user, ?object $legacyUser, MigrationReport $report): void
    {
        $token = trim((string) ($legacyUser->api_token ?? ''));
        if ($token === '') {
            return;
        }

        if ((string) $user->api_token === $token) {
            app(ApiTokenService::class)->ensure($user);
            $report->bump('api_token', 'updated');

            return;
        }

        $user->forceFill(['api_token' => $token])->save();
        app(ApiTokenService::class)->ensure($user);
        $report->bump('api_token', 'created');
    }

    private function importNotificationContacts(
        User $user,
        ?object $legacyUser,
        MigrationIdMap $ids,
        MigrationReport $report,
    ): void {
        if ($legacyUser === null || ! $this->legacy->tableExists('notification_contacts')) {
            return;
        }

        $rows = $this->legacy->db()->table('notification_contacts')
            ->where('user_id', (int) $legacyUser->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $type = $this->mapNotificationType($row->type ?? null);
            $info = trim((string) ($row->contact_info ?? ''));
            if ($info === '') {
                $report->bump('notification_contacts', 'skipped');

                continue;
            }

            $existingId = $ids->getInt('notification_contact', $legacyId);
            $existing = $existingId
                ? NotificationContact::query()->find($existingId)
                : NotificationContact::query()
                    ->where('user_id', $user->id)
                    ->where('type', $type->value)
                    ->where('contact_info', $info)
                    ->first();

            $attributes = [
                'user_id' => $user->id,
                'full_name' => filled($row->full_name ?? null) ? (string) $row->full_name : null,
                'type' => $type,
                'contact_info' => $info,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $contact = $existing;
                $report->bump('notification_contacts', 'updated');
            } else {
                $contact = NotificationContact::query()->create($attributes);
                $report->bump('notification_contacts', 'created');
            }

            $ids->put('notification_contact', $legacyId, $contact->id);
        }
    }

    private function mapNotificationType(mixed $raw): NotificationContactType
    {
        $value = strtolower(trim((string) $raw));

        return $value === 'whatsapp'
            ? NotificationContactType::Whatsapp
            : NotificationContactType::Email;
    }
}
