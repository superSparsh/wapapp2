<?php

declare(strict_types=1);

namespace Tests\Feature\LegacyMigration;

use App\Domains\LegacyMigration\DTO\MigrationOptions;
use App\Domains\LegacyMigration\Services\CustomerMigrationOrchestrator;
use App\Domains\LegacyMigration\Services\LegacyCustomerResolver;
use App\Enums\BillingCycle;
use App\Enums\TenantStatus;
use App\Models\Contact;
use App\Models\LegacyCustomerMigration;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\User;
use App\Models\WhatsappLine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyCustomerMigrationTest extends TestCase
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
        $this->seedPlan();
    }

    public function test_list_and_pilot_resolution(): void
    {
        $this->seedLegacyCustomer(1, 'pilot@example.com', withExtras: true);
        $this->seedLegacyCustomer(2, 'tiny@example.com', withExtras: false);

        $resolver = app(LegacyCustomerResolver::class);
        $pilot = $resolver->resolvePilot();

        $this->assertSame(1, $pilot->id);
        $this->assertSame('pilot@example.com', $pilot->email);
    }

    public function test_migrates_single_customer_without_duplicates_on_rerun(): void
    {
        $this->seedLegacyCustomer(10, 'owner@example.com', withExtras: true);

        $orchestrator = app(CustomerMigrationOrchestrator::class);
        $options = new MigrationOptions(dryRun: false, force: false);

        $first = $orchestrator->migrate('owner@example.com', $options);
        $this->assertNotNull($first['tenant_id']);
        $this->assertTrue($first['created_tenant']);

        $tenant = Tenant::query()->findOrFail($first['tenant_id']);
        tenancy()->initialize($tenant);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, WhatsappLine::query()->count());
        $this->assertSame(1, Contact::query()->count());
        $this->assertDatabaseHas('templates', [
            'code' => 'CODE_WELCOME_10',
            'name' => 'welcome_10',
            'source' => 'local',
        ]);

        tenancy()->end();

        $this->assertTrue(
            TenantUserAccess::query()
                ->where('email', 'owner@example.com')
                ->where('tenant_id', $tenant->id)
                ->exists()
        );

        $second = $orchestrator->migrate('10', new MigrationOptions(dryRun: false, force: true));
        $this->assertSame($first['tenant_id'], $second['tenant_id']);
        $this->assertFalse($second['created_tenant']);

        tenancy()->initialize($tenant);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, WhatsappLine::query()->count());
        $this->assertSame(1, Contact::query()->count());
        tenancy()->end();

        $this->assertDatabaseHas('legacy_customer_migrations', [
            'legacy_customer_id' => 10,
            'tenant_id' => $tenant->id,
            'status' => 'completed',
        ]);
    }

    public function test_dry_run_does_not_create_tenant(): void
    {
        $this->seedLegacyCustomer(11, 'dry@example.com', withExtras: true);

        $before = Tenant::query()->count();
        $result = app(CustomerMigrationOrchestrator::class)->migrate(
            '11',
            new MigrationOptions(dryRun: true),
        );

        $this->assertTrue($result['dry_run']);
        $this->assertSame($before, Tenant::query()->count());
        $this->assertSame(0, LegacyCustomerMigration::query()->where('status', 'completed')->count());
    }

    public function test_migrates_waba_id_from_legacy_business_infos(): void
    {
        $this->seedLegacyCustomer(20, 'waba@example.com', withExtras: true);

        DB::connection('legacy')->table('business_infos')->insert([
            [
                'customer_id' => 20,
                'waba_id' => 'WABA-LEGACY-20',
                'cust_space_id' => 'SPACE-LEGACY-20',
                'business_name' => 'Legacy Biz',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'customer_id' => 20,
                'waba_id' => '',
                'cust_space_id' => '',
                'business_name' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $result = app(CustomerMigrationOrchestrator::class)->migrate(
            'waba@example.com',
            new MigrationOptions(dryRun: false, force: false),
        );

        $tenant = Tenant::query()->findOrFail($result['tenant_id']);
        tenancy()->initialize($tenant);

        $line = WhatsappLine::query()->first();
        $this->assertNotNull($line);
        $this->assertSame('WABA-LEGACY-20', $line->waba_id);
        $this->assertSame('SPACE-LEGACY-20', $line->alibaba_cust_space_id);
        $this->assertSame('Legacy Biz', $line->metadata['business_name'] ?? null);
        $this->assertTrue($line->isConnected());

        tenancy()->end();
    }

    public function test_force_reimport_copies_waba_onto_existing_line(): void
    {
        $this->seedLegacyCustomer(21, 'refill@example.com', withExtras: true);

        $first = app(CustomerMigrationOrchestrator::class)->migrate(
            'refill@example.com',
            new MigrationOptions(dryRun: false, force: false),
        );

        $tenant = Tenant::query()->findOrFail($first['tenant_id']);
        tenancy()->initialize($tenant);
        $this->assertTrue(blank(WhatsappLine::query()->value('waba_id')));
        tenancy()->end();

        DB::connection('legacy')->table('business_infos')->insert([
            'customer_id' => 21,
            'waba_id' => 'WABA-REFILL-21',
            'cust_space_id' => 'SPACE-REFILL-21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CustomerMigrationOrchestrator::class)->migrate(
            'refill@example.com',
            new MigrationOptions(dryRun: false, force: true),
        );

        tenancy()->initialize($tenant);
        $line = WhatsappLine::query()->first();
        $this->assertSame('WABA-REFILL-21', $line?->waba_id);
        $this->assertSame('SPACE-REFILL-21', $line?->alibaba_cust_space_id);
        tenancy()->end();
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
            $table->string('quality_rating')->nullable();
            $table->string('message_limiter')->nullable();
            $table->string('verification_status')->nullable();
            $table->timestamps();
        });

        $schema->create('business_infos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('waba_id')->nullable();
            $table->text('cust_space_id')->nullable();
            $table->text('waba_response')->nullable();
            $table->text('cust_response')->nullable();
            $table->string('business_id')->nullable();
            $table->string('business_name')->nullable();
            $table->string('status')->nullable();
            $table->string('vertical')->nullable();
            $table->timestamps();
        });

        $schema->create('mail_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('uid')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('new_contact_id')->nullable();
            $table->string('name');
            $table->string('default_subject')->nullable();
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
            $table->string('country_code')->nullable();
            $table->text('tags')->nullable();
            $table->timestamps();
        });

        $schema->create('new_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('uid')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('new_contact_id')->nullable();
            $table->string('template_name');
            $table->string('template_code')->nullable();
            $table->string('language')->nullable();
            $table->string('status')->nullable();
            $table->text('body')->nullable();
            $table->text('actual_body')->nullable();
            $table->string('team_member_name')->nullable();
            $table->timestamps();
        });

        foreach (['automation_bots', 'automation2s', 'flows', 'team_members', 'new_campaigns', 'ai_bots', 'wallet_transactions', 'sub_replies', 'conversations'] as $table) {
            if ($schema->hasTable($table)) {
                continue;
            }
            $schema->create($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->id();
                if (in_array($table, ['team_members'], true)) {
                    $blueprint->unsignedBigInteger('parent_id')->nullable();
                } else {
                    $blueprint->unsignedBigInteger('customer_id')->nullable();
                }
                $blueprint->timestamps();
            });
        }
    }

    private function seedLegacyCustomer(int $id, string $email, bool $withExtras): void
    {
        DB::connection('legacy')->table('customers')->insert([
            'id' => $id,
            'uid' => 'uid-'.$id,
            'wallet_amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('users')->insert([
            'customer_id' => $id,
            'email' => $email,
            'company_name' => 'Company '.$id,
            'first_name' => 'Owner',
            'last_name' => (string) $id,
            'phone' => '98765432'.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('new_contacts')->insert([
            'id' => $id * 10,
            'customer_id' => $id,
            'is_default' => 1,
            'phone' => '9198765432'.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
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
            'name' => 'Main List '.$id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('subscribers')->insert([
            'mail_list_id' => $listId,
            'email' => 'contact'.$id.'@example.com',
            'phone_number' => '98765000'.str_pad((string) $id, 2, '0', STR_PAD_LEFT),
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
            'template_code' => 'CODE_WELCOME_'.$id,
            'language' => 'en',
            'status' => 'approved',
            'body' => 'Hello {{1}}',
            'actual_body' => 'Hello {{1}}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
