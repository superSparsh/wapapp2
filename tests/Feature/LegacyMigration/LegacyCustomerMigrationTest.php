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
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
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

    public function test_migrates_webhook_subscriptions_and_delivery_logs(): void
    {
        $this->seedLegacyCustomer(40, 'webhooks@example.com', withExtras: true);

        $result = app(CustomerMigrationOrchestrator::class)->migrate(
            '40',
            new MigrationOptions(dryRun: false, force: false),
        );

        $tenant = Tenant::query()->findOrFail($result['tenant_id']);
        tenancy()->initialize($tenant);

        $this->assertSame(1, WebhookSubscription::query()->count());
        $subscription = WebhookSubscription::query()->firstOrFail();
        $this->assertSame('https://hooks.example.com/leads/40', $subscription->url);
        $this->assertSame('secret-legacy-40', $subscription->secret_key);
        $this->assertSame(['new_lead'], $subscription->events);
        $this->assertNotNull($subscription->whatsapp_line_id);
        $this->assertNotNull($subscription->audience_list_id);

        $this->assertSame(1, WebhookDelivery::query()->count());
        $delivery = WebhookDelivery::query()->firstOrFail();
        $this->assertSame($subscription->id, $delivery->webhook_subscription_id);
        $this->assertSame('new_lead', $delivery->event_type);
        $this->assertSame(200, $delivery->response_status);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            (string) $delivery->correlation_id,
        );
        $this->assertSame('Lead 40', $delivery->payload['data']['name'] ?? null);
        $this->assertSame(40000, $delivery->payload['_legacy']['webhook_id'] ?? null);
        $this->assertIsInt($delivery->payload['_legacy']['webhook_log_id'] ?? null);
        tenancy()->end();

        // Re-run must not duplicate.
        app(CustomerMigrationOrchestrator::class)->migrate(
            '40',
            new MigrationOptions(dryRun: false, force: true),
        );

        tenancy()->initialize($tenant);
        $this->assertSame(1, WebhookSubscription::query()->count());
        $this->assertSame(1, WebhookDelivery::query()->count());
        tenancy()->end();
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

    public function test_migrates_ai_keys_settings_and_knowledge_base(): void
    {
        $this->seedLegacyCustomer(30, 'ai@example.com', withExtras: true);

        DB::connection('legacy')->table('customers')->where('id', 30)->update([
            'openai_api_key' => 'sk-legacy-customer-fallback-key-30',
            'ai_response' => 1,
            'business_information' => json_encode([
                'business_information' => 'We sell widgets and support WhatsApp commerce.',
            ]),
        ]);

        DB::connection('legacy')->table('provider_api_keys')->insert([
            'customer_id' => 30,
            'provider' => 'openai',
            'api_key' => 'sk-provider-key-from-legacy-30',
            'chat_model' => 'gpt-4o-mini',
            'embedding_model' => 'text-embedding-3-small',
            'is_active' => 1,
            'is_validated' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('ai_bots')->insert([
            'customer_id' => 30,
            'name' => 'Support Bot',
            'type' => 'assistant',
            'provider' => 'openai',
            'chat_model' => 'gpt-4o-mini',
            'status' => 'active',
            'is_default' => 1,
            'business_information' => 'Bot-specific FAQ text',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(CustomerMigrationOrchestrator::class)->migrate(
            'ai@example.com',
            new MigrationOptions(dryRun: false, force: false),
        );

        $tenant = Tenant::query()->findOrFail($result['tenant_id']);
        tenancy()->initialize($tenant);

        $this->assertTrue(\App\Models\AiSetting::getBool('ai_auto_response_enabled', false));
        $this->assertDatabaseHas('ai_provider_keys', [
            'provider' => 'openai',
        ]);
        $key = \App\Models\AiProviderKey::query()->where('provider', 'openai')->first();
        $this->assertNotNull($key);
        $this->assertSame('sk-provider-key-from-legacy-30', $key->api_key);

        $this->assertTrue(
            \App\Models\AiBusinessInfo::query()
                ->where('title', 'Migrated business information')
                ->where('content', 'We sell widgets and support WhatsApp commerce.')
                ->exists()
        );

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
            $table->text('openai_api_key')->nullable();
            $table->boolean('ai_response')->default(false);
            $table->longText('business_information')->nullable();
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
            $table->string('header_type')->nullable();
            $table->text('header_media')->nullable();
            $table->text('header_desc')->nullable();
            $table->text('body')->nullable();
            $table->text('actual_body')->nullable();
            $table->string('team_member_name')->nullable();
            $table->timestamps();
        });

        $schema->create('provider_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->string('provider', 20);
            $table->text('api_key');
            $table->string('chat_model', 100)->nullable();
            $table->string('embedding_model', 100)->nullable();
            $table->integer('embedding_dimensions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_validated')->default(false);
            $table->timestamps();
        });

        $schema->create('ai_bots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->text('system_prompt')->nullable();
            $table->string('provider')->nullable();
            $table->string('chat_model')->nullable();
            $table->string('model_name')->nullable();
            $table->string('embedding_model')->nullable();
            $table->float('temperature')->nullable();
            $table->longText('business_information')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedBigInteger('new_contact_id')->nullable();
            $table->timestamps();
        });

        $schema->create('webhook_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('new_contact_id')->nullable();
            $table->string('url');
            $table->string('description')->nullable();
            $table->string('secret_key');
            $table->text('events');
            $table->string('status')->default('active');
            $table->timestamp('last_triggered_at')->nullable();
            $table->unsignedInteger('audience_list_id')->nullable();
            $table->timestamps();
        });

        $schema->create('webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('customer_id');
            $table->unsignedBigInteger('new_contact_id')->nullable();
            $table->unsignedBigInteger('webhook_id')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('event_type');
            $table->text('payload');
            $table->text('response_body')->nullable();
            $table->integer('response_status')->nullable();
            $table->text('error_message')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->timestamps();
        });

        foreach (['automation_bots', 'automation2s', 'flows', 'team_members', 'new_campaigns', 'wallet_transactions', 'sub_replies', 'conversations'] as $table) {
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

        $webhookId = $id * 1000;
        DB::connection('legacy')->table('webhook_settings')->insert([
            'id' => $webhookId,
            'customer_id' => $id,
            'new_contact_id' => $id * 10,
            'url' => 'https://hooks.example.com/leads/'.$id,
            'description' => 'Legacy webhook '.$id,
            'secret_key' => 'secret-legacy-'.$id,
            'events' => json_encode(['new_lead']),
            'status' => 'active',
            'audience_list_id' => $listId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('webhook_logs')->insert([
            'customer_id' => $id,
            'new_contact_id' => $id * 10,
            'webhook_id' => $webhookId,
            'webhook_url' => 'https://hooks.example.com/leads/'.$id,
            'event_type' => 'new_lead',
            'payload' => json_encode(['phone' => '919876543210', 'name' => 'Lead '.$id]),
            'response_body' => '{"ok":true}',
            'response_status' => 200,
            'error_message' => null,
            'status' => 'success',
            'sent_at' => now()->subHour(),
            'response_received_at' => now()->subHour()->addSeconds(2),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
    }
}
