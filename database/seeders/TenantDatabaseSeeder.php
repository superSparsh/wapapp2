<?php

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Enums\TenantUserAccountType;
use App\Enums\UserRole;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'owner@demo.wapapp.test'],
            [
                'name' => 'Demo Owner',
                'first_name' => 'Demo',
                'last_name' => 'Owner',
                'phone' => '919999999999',
                'password' => 'password',
                'role' => UserRole::Owner,
                'email_verified_at' => now(),
                'api_token' => Str::random(60),
            ],
        );

        WhatsappLine::query()->updateOrCreate(
            ['phone' => '919999999999'],
            [
                'display_name' => 'Demo Business Line',
                'status' => RecordStatus::Active,
                'is_default' => true,
            ],
        );

        $tenantId = tenant('id');

        tenancy()->central(function () use ($tenantId, $user): void {
            TenantUserAccess::query()->updateOrCreate(
                ['email' => $user->email],
                [
                    'tenant_id' => $tenantId,
                    'phone' => PhoneNormalizer::normalize($user->phone),
                    'account_type' => TenantUserAccountType::Owner,
                    'is_active' => true,
                    'api_token' => $user->api_token,
                ],
            );
        });

        $this->command?->info('Tenant seeded: owner@demo.wapapp.test / password');
        $this->command?->info('Mobile OTP login: 9999999999');

        $this->call(InboxDemoSeeder::class);
    }
}
