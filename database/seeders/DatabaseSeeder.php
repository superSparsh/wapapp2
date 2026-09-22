<?php

namespace Database\Seeders;

use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Domains\Tenancy\Services\TenantProvisioner;
use App\Enums\BillingCycle;
use App\Enums\TenantStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'For growing businesses with campaigns and automation.',
            'price' => 4999,
            'currency' => 'INR',
            'billing_cycle' => BillingCycle::Monthly,
            'messages_limit' => 50000,
            'contacts_limit' => 10000,
            'team_members_limit' => 10,
            'whatsapp_lines_limit' => 5,
            'sort_order' => 1,
            'is_active' => true,
            'features' => [
                'inbox' => true,
                'campaigns' => true,
                'automation' => true,
                'webhooks' => true,
            ],
        ]);

        $this->call(PlatformDefaultsSeeder::class);

        $adminEmail = strtolower(trim((string) env('ADMIN_SEED_EMAIL', 'superadmin@wapapp.in')));
        $adminPassword = (string) env('ADMIN_SEED_PASSWORD', '');
        if ($adminPassword === '') {
            $adminPassword = Str::password(28, symbols: true);
        }

        Admin::query()->create([
            'name' => 'Super Admin',
            'email' => $adminEmail,
            'password' => $adminPassword,
            'admin_role_id' => AdminRole::query()->where('slug', 'super-admin')->value('id'),
        ]);

        $this->call(HelpCenterSeeder::class);

        $tenantId = 'demo-0001';
        $databaseName = app(TenantDatabaseNamingService::class)->forTenantId($tenantId);

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'database_name' => $databaseName,
            'name' => 'Demo Company',
            'company_name' => 'Demo Company Pvt Ltd',
            'email' => 'demo@wapapp.test',
            'status' => TenantStatus::Active,
            'plan_id' => $plan->id,
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'country_code' => 'IN',
            'provisioned_at' => now(),
        ]);

        $tenant->domains()->create([
            'domain' => $tenantId,
            'is_primary' => true,
        ]);

        app(TenantProvisioner::class)->ensureDatabase($tenant);

        $tenant->run(function (): void {
            $this->call(TenantDatabaseSeeder::class);
        });

        $this->command?->info('Master DB seeded successfully.');
        $this->command?->info("Admin login: {$adminEmail}");
        $this->command?->warn("Admin password: {$adminPassword}");
        $this->command?->info('Set ADMIN_SEED_EMAIL / ADMIN_SEED_PASSWORD in .env to use fixed credentials.');
        $this->command?->info('Tenant: '.$tenantId.' (auto-provisioned)');
        $this->command?->info('Tenant DB: '.$databaseName);
    }
}
