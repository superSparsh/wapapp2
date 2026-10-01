<?php

declare(strict_types=1);

namespace Tests\Feature\MobileApi;

use App\Domains\MobileApi\Services\JwtTokenService;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\TenantUserAccountType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\TenantUserAccess;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class MobileInboxTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private string $accessToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        WalletAccount::query()->create([
            'balance' => 500,
            'currency' => 'INR',
        ]);

        TenantUserAccess::query()->updateOrCreate(
            ['email' => strtolower($this->testUser->email)],
            [
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'phone' => $this->testUser->phone,
            ],
        );

        $this->accessToken = app(JwtTokenService::class)
            ->issuePair($this->testUser, $this->testTenant->id, 'web')['access_token'];

        tenancy()->end();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_assigned_numbers_requires_jwt(): void
    {
        $this->getJson('/api/v1/mobile/inbox/assigned-numbers')
            ->assertStatus(401);
    }

    public function test_assigned_numbers_lists_lines(): void
    {
        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/assigned-numbers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.phone', '919999999999');
    }

    public function test_conversations_and_dashboard(): void
    {
        tenancy()->initialize($this->testTenant);

        $contact = Contact::factory()->create(['name' => 'Mobile User', 'phone' => '918888888801']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
            'unread_count' => 1,
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Hello mobile',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
        ]);

        tenancy()->end();

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/conversations?whatsapp_number=919999999999')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['unread_messages', 'tenant_id']]);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/new-message-count')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/plan-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_id', $this->testTenant->id);
    }

    public function test_device_token_via_jwt(): void
    {
        tenancy()->initialize($this->testTenant);
        if (! \Illuminate\Support\Facades\Schema::hasTable('fcm_tokens')) {
            $this->artisan('tenants:migrate', [
                '--tenants' => [$this->testTenant->id],
                '--force' => true,
            ]);
        }
        tenancy()->end();

        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/device-token', [
                'token' => str_repeat('m', 40),
                'platform' => 'android',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        tenancy()->initialize($this->testTenant);
        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $this->testUser->id,
            'token' => str_repeat('m', 40),
        ]);
    }

    public function test_add_contact(): void
    {
        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/inbox/contacts', [
                'whatsapp_number' => '919999999999',
                'phone' => '918777777701',
                'name' => 'New Mobile Contact',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.customer_name', 'New Mobile Contact');
    }

    public function test_provider_keys_and_ai_endpoints(): void
    {
        $this->withToken($this->accessToken)
            ->postJson('/api/v1/mobile/inbox/ai/openai-key', [
                'api_key' => 'sk-test-mobile-key-123456',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/ai/openai-key')
            ->assertOk()
            ->assertJsonPath('data.configured', true);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/provider-keys')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/usage/summary')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
