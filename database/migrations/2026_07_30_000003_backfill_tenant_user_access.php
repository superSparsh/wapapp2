<?php

declare(strict_types=1);

use App\Enums\TenantUserAccountType;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            tenancy()->initialize($tenant);

            User::query()->where('role', 'owner')->each(function (User $user) use ($tenant): void {
                tenancy()->central(function () use ($tenant, $user): void {
                    TenantUserAccess::query()->updateOrCreate(
                        ['email' => strtolower($user->email)],
                        [
                            'tenant_id' => $tenant->id,
                            'account_type' => TenantUserAccountType::Owner,
                            'is_active' => true,
                        ],
                    );
                });
            });

            tenancy()->end();
        });
    }

    public function down(): void
    {
        TenantUserAccess::query()->delete();
    }
};
