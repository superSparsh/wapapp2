<?php

declare(strict_types=1);

use App\Enums\TenantUserAccountType;
use App\Models\TeamMember;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            tenancy()->initialize($tenant);

            User::query()->whereNotNull('phone')->each(function (User $user) use ($tenant): void {
                $this->syncPhone($tenant->id, $user->email, $user->phone, TenantUserAccountType::Owner);
            });

            TeamMember::query()->whereNotNull('phone')->each(function (TeamMember $member) use ($tenant): void {
                $this->syncPhone($tenant->id, $member->email, $member->phone, TenantUserAccountType::Team);
            });

            tenancy()->end();
        });
    }

    private function syncPhone(string $tenantId, string $email, ?string $phone, TenantUserAccountType $type): void
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === null) {
            return;
        }

        tenancy()->central(function () use ($tenantId, $email, $normalized, $type): void {
            TenantUserAccess::query()->updateOrCreate(
                ['email' => strtolower($email)],
                [
                    'tenant_id' => $tenantId,
                    'account_type' => $type,
                    'phone' => $normalized,
                    'is_active' => true,
                ],
            );
        });
    }

    public function down(): void
    {
        TenantUserAccess::query()->update(['phone' => null]);
    }
};
