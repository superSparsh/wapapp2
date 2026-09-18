<?php

declare(strict_types=1);

namespace Tests\Feature\LegacyMigration;

use App\Domains\LegacyMigration\Services\LegacyPlanImportService;
use App\Domains\LegacyMigration\Services\LegacySettingsImportService;
use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyAdminImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('legacy-migration.connection', 'legacy');

        $this->createLegacySchema();
    }

    public function test_import_settings_maps_known_admin_keys(): void
    {
        DB::connection('legacy')->table('settings')->insert([
            ['name' => 'site_name', 'value' => 'Tekkonnect Legacy'],
            ['name' => 'mailer.from.address', 'value' => 'noreply@legacy.test'],
            ['name' => 'mailer.from.name', 'value' => 'Legacy Mailer'],
            ['name' => 'conversion_price', 'value' => '84.25'],
            ['name' => 'wallet_balance_unit', 'value' => 'usd'],
            ['name' => 'cashier.razorpay.key_id', 'value' => 'rzp_test_key'],
            ['name' => 'cashier.razorpay.key_secret', 'value' => 'rzp_test_secret'],
            ['name' => 'gateways', 'value' => '["razorpay","offline"]'],
            ['name' => 'tax', 'value' => json_encode(['enabled' => true, 'rate' => 18, 'countries' => ['IN']])],
            ['name' => 'some_unknown_key', 'value' => 'keep-me'],
        ]);

        $stats = app(LegacySettingsImportService::class)->import(dryRun: false, includeExtras: true);

        $this->assertSame(7, $stats['mapped']);
        $this->assertGreaterThan(0, $stats['derived']);
        $this->assertSame(1, $stats['extras']);

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'general.app_name',
            'value' => 'Tekkonnect Legacy',
        ], config('tenancy.database.central_connection'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'wallet.conversion_price',
            'value' => '84.25',
        ], config('tenancy.database.central_connection'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'payment.razorpay_enabled',
            'value' => '1',
        ], config('tenancy.database.central_connection'));

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'legacy.some_unknown_key',
            'value' => 'keep-me',
        ], config('tenancy.database.central_connection'));

        $this->assertSame('Tekkonnect Legacy', PlatformSetting::query()->where('key', 'general.app_name')->value('value'));
    }

    public function test_import_plans_and_assign_tenant_from_legacy_subscription(): void
    {
        DB::connection('legacy')->table('plans')->insert([
            'id' => 10,
            'uid' => 'plan-uid-10',
            'name' => 'Growth Legacy',
            'description' => 'Legacy growth',
            'price' => 1999,
            'frequency_unit' => 'month',
            'status' => 'active',
            'options' => json_encode([
                'email_max' => 25000,
                'subscriber_max' => 8000,
                'max_users' => 5,
                'max_whatsapp_lines' => 3,
            ]),
            'ai_response' => 1,
            'is_international_plan' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('subscriptions')->insert([
            'customer_id' => 55,
            'plan_id' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dummy = Plan::query()->create([
            'name' => 'Professional',
            'slug' => 'professional',
            'price' => 0,
            'currency' => 'INR',
            'billing_cycle' => BillingCycle::Monthly,
            'is_active' => true,
            'sort_order' => 1,
            'features' => ['source' => 'auto_seed'],
        ]);

        $tenant = Tenant::withoutEvents(function () use ($dummy) {
            return Tenant::query()->create([
                'id' => 'legacy-cust-55',
                'name' => 'Legacy Customer 55',
                'email' => 'cust55@example.com',
                'status' => 'active',
                'plan_id' => $dummy->id,
                'settings' => ['legacy_customer_id' => 55],
            ]);
        });

        $service = app(LegacyPlanImportService::class);
        $planStats = $service->import(dryRun: false, deactivateNonLegacy: true);
        $assignStats = $service->assignTenantPlans(dryRun: false);

        $this->assertSame(1, $planStats['created']);
        $this->assertSame(1, $planStats['deactivated']);
        $this->assertSame(1, $assignStats['assigned']);

        $imported = Plan::query()->where('name', 'Growth Legacy')->first();
        $this->assertNotNull($imported);
        $this->assertSame(10, (int) data_get($imported->features, 'legacy_plan_id'));
        $this->assertSame(25000, $imported->messages_limit);
        $this->assertSame(8000, $imported->contacts_limit);
        $this->assertFalse($dummy->fresh()->is_active);

        $this->assertSame($imported->id, $tenant->fresh()->plan_id);
    }

    private function createLegacySchema(): void
    {
        $legacy = 'legacy';

        Schema::connection($legacy)->create('customers', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('uid')->nullable();
        });

        Schema::connection($legacy)->create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('email')->nullable();
        });

        Schema::connection($legacy)->create('new_contacts', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('customer_id')->nullable();
        });

        Schema::connection($legacy)->create('settings', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->text('value')->nullable();
        });

        Schema::connection($legacy)->create('plans', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('uid')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('frequency_unit')->nullable();
            $table->string('status')->nullable();
            $table->text('options')->nullable();
            $table->boolean('ai_response')->default(false);
            $table->boolean('is_international_plan')->default(false);
            $table->string('credit_option')->nullable();
            $table->timestamps();
        });

        Schema::connection($legacy)->create('subscriptions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('plan_id');
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
}
