<?php

declare(strict_types=1);

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

            User::query()
                ->where('email', 'owner@demo.wapapp.test')
                ->whereNull('phone')
                ->update(['phone' => '919999999999']);

            $user = User::query()->where('email', 'owner@demo.wapapp.test')->first();

            if ($user?->phone) {
                tenancy()->central(function () use ($tenant, $user): void {
                    TenantUserAccess::query()
                        ->where('email', $user->email)
                        ->update(['phone' => PhoneNormalizer::normalize($user->phone)]);
                });
            }

            tenancy()->end();
        });
    }

    public function down(): void
    {
        //
    }
};
