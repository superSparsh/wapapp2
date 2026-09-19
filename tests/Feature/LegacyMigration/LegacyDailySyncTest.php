<?php

declare(strict_types=1);

namespace Tests\Feature\LegacyMigration;

use App\Domains\LegacyMigration\DTO\MigrationOptions;
use App\Domains\LegacyMigration\Services\CustomerMigrationOrchestrator;
use App\Domains\LegacyMigration\Services\LegacyCustomerResolver;
use App\Enums\BillingCycle;
use App\Models\Contact;
use App\Models\LegacyCustomerMigration;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappLine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyDailySyncTest extends TestCase
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
        Config::set('legacy-migration.daily_sync.enabled', true);
        Config::set('legacy-migration.daily_sync.import_plans', false);
        Config::set('legacy-migration.daily_sync.import_settings', false);

        $this->createLegacySchema();
        $this->seedPlan();
    }

    public function test_sync_queue_lists_known_then_new_customers(): void
    {
        $this->seedLegacyCustomer(1, 'a@example.com');
        $this->seedLegacyCustomer(2, 'b@example.com');

        LegacyCustomerMigration::query()->create([
            'legacy_customer_id' => 2,
            'legacy_email' => 'b@example.com',
            'status' => 'completed',
        ]);

        $ids = app(LegacyCustomerResolver::class)->listSyncCustomerIds()->all();

        $this->assertSame([2, 1], $ids);
    }

    public function test_sync_all_migrates_new_and_force_resyncs_without_duplicates(): void
    {
        $this->seedLegacyCustomer(10, 'one@example.com', withExtras: true);
        $this->seedLegacyCustomer(11, 'two@example.com', withExtras: true);

        $orchestrator = app(CustomerMigrationOrchestrator::class);
        $options = new MigrationOptions(dryRun: false, force: true, skipInbox: true);

        $firstPass = $orchestrator->syncAll($options);
        $this->assertCount(2, $firstPass);
        $this->assertNull($firstPass[0]['error']);
        $this->assertNull($firstPass[1]['error']);

        $tenantIds = collect($firstPass)->pluck('tenant_id')->filter()->unique()->values();
        $this->assertCount(2, $tenantIds);

        foreach ($firstPass as $result) {
            $tenant = Tenant::query()->findOrFail($result['tenant_id']);
            tenancy()->initialize($tenant);
            $this->assertSame(1, User::query()->count());
            $this->assertSame(1, WhatsappLine::query()->count());
            $this->assertSame(1, Contact::query()->count());
            tenancy()->end();
        }

        // New contact on legacy for customer 10 — second sync should upsert, not duplicate lines/users.
        DB::connection('legacy')->table('subscribers')->insert([
            'mail_list_id' => 1000,
            'email' => 'extra10@example.com',
            'phone_number' => '9876500099',
            'first_name' => 'Extra',
            'last_name' => '10',
            'status' => 'subscribed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondPass = $orchestrator->syncAll($options);
        $this->assertCount(2, $secondPass);
        $this->assertSame($firstPass[0]['tenant_id'], $secondPass[0]['tenant_id']);

        $tenant = Tenant::query()->findOrFail($secondPass[0]['tenant_id']);
        tenancy()->initialize($tenant);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, WhatsappLine::query()->count());
        $this->assertSame(2, Contact::query()->count());
        tenancy()->end();
    }

    public function test_sync_daily_command_runs_successfully(): void
    {
        $this->seedLegacyCustomer(20, 'cmd@example.com', withExtras: true);

        $this->artisan('legacy:sync-daily', [
            '--skip-central' => true,
            '--skip-inbox' => true,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('legacy_customer_migrations', [
            'legacy_customer_id' => 20,
            'status' => 'completed',
        ]);
    }

    public function test_skips_customer_still_marked_running(): void
    {
        $this->seedLegacyCustomer(30, 'busy@example.com', withExtras: true);

        LegacyCustomerMigration::query()->create([
            'legacy_customer_id' => 30,
            'legacy_email' => 'busy@example.com',
            'status' => 'running',
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        $results = app(CustomerMigrationOrchestrator::class)->syncAll(
            new MigrationOptions(dryRun: false, force: true, skipInbox: true),
        );

        $this->assertTrue($results[0]['skipped'] ?? false);
        $this->assertSame('already_running', $results[0]['skip_reason'] ?? null);
    }

    private function seedPlan(): void
    {
        Plan::query()->create([
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'Test plan',
            'price' => 1,
            'currency' => 'INR',
            'billing_cycle' => BillingCycle::Monthly,
            'messages_limit' => 1000,
            'contacts_limit' => 1000,
            'team_members_limit' => 5,
            'whatsapp_lines_limit' => 2,
            'sort_order' => 1,
            'is_active' => true,
            'features' => [],
        ]);
    }

    private function createLegacySchema(): void
    {
        $schema = Schema::connection('legacy');

        $schema->create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('uid')->nullable();
            $table->decimal('wallet_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('new_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->boolean('is_default')->default(true);
            $table->string('phone');
            $table->string('verified_name')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('mail_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('uid')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('new_contact_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('uid')->nullable();
            $table->unsignedBigInteger('mail_list_id');
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('new_templates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('new_contact_id')->nullable();
            $table->string('template_name');
            $table->string('template_code')->nullable();
            $table->string('language')->nullable();
            $table->string('status')->nullable();
            $table->text('body')->nullable();
            $table->text('actual_body')->nullable();
            $table->timestamps();
        });

        foreach (['automation_bots', 'automation2s', 'flows', 'team_members', 'new_campaigns', 'wallet_transactions', 'sub_replies', 'business_infos'] as $table) {
            $schema->create($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->id();
                if ($table === 'team_members') {
                    $blueprint->unsignedBigInteger('parent_id')->nullable();
                } else {
                    $blueprint->unsignedBigInteger('customer_id')->nullable();
                }
                $blueprint->timestamps();
            });
        }
    }

    private function seedLegacyCustomer(int $id, string $email, bool $withExtras = false): void
    {
        DB::connection('legacy')->table('customers')->insert([
            'id' => $id,
            'uid' => 'uid-'.$id,
            'wallet_amount' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('users')->insert([
            'customer_id' => $id,
            'email' => $email,
            'company_name' => 'Co '.$id,
            'first_name' => 'Owner',
            'last_name' => (string) $id,
            'phone' => '90000000'.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('new_contacts')->insert([
            'id' => $id * 10,
            'customer_id' => $id,
            'is_default' => 1,
            'phone' => '9190000000'.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            'verified_name' => 'Line '.$id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $withExtras) {
            return;
        }

        $listId = $id * 100;
        DB::connection('legacy')->table('mail_lists')->insert([
            'id' => $listId,
            'uid' => 'list-'.$id,
            'customer_id' => $id,
            'new_contact_id' => $id * 10,
            'name' => 'List '.$id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('subscribers')->insert([
            'mail_list_id' => $listId,
            'email' => 'c'.$id.'@example.com',
            'phone_number' => '98880000'.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            'first_name' => 'Contact',
            'last_name' => (string) $id,
            'status' => 'subscribed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('new_templates')->insert([
            'customer_id' => $id,
            'new_contact_id' => $id * 10,
            'template_name' => 'welcome_'.$id,
            'template_code' => 'CODE_'.$id,
            'language' => 'en',
            'status' => 'approved',
            'body' => 'Hello',
            'actual_body' => 'Hello',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
