<?php

namespace Database\Seeders;

use App\Domains\Tenancy\Services\TenantDatabaseNamingService;
use App\Domains\Tenancy\Services\TenantProvisioner;
use App\Enums\BillingCycle;
use App\Enums\TenantStatus;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Tenant;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Database\Seeder;

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

        Admin::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@wapapp.test',
            'password' => 'password',
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
        $this->command?->info('Admin: admin@wapapp.test / password');
        $this->command?->info('Tenant: '.$tenantId.' (auto-provisioned)');
        $this->command?->info('Tenant DB: '.$databaseName);
    }
}
