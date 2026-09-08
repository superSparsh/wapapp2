<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Services\TenantBootstrapper;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\UserRole;
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
        $this->bootstrapper->ensureOwnerAccess($tenant, $customer->email, $customer->phone);
    }
}
