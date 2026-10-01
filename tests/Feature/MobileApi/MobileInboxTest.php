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
        $response = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/assigned-numbers')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertIsArray($response->json('data'));
        $this->assertIsArray($response->json('data.numbers'));
        $this->assertSame('919999999999', $response->json('data.numbers.0.phone'));
        $this->assertArrayHasKey('userassigned', $response->json('data'));
        // Legacy InboxService also exposes wallet_amount at the response root.
        $this->assertSame('500.00', $response->json('wallet_amount'));
        $this->assertSame('500.00', $response->json('data.wallet_amount'));
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

        $list = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/conversations?whatsapp_number=919999999999')
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertIsArray($list->json('data'));
        $this->assertIsArray($list->json('data.data'));
        $this->assertSame('Mobile User', $list->json('data.data.0.customer_name'));
        $this->assertSame('918888888801', $list->json('data.data.0.msg_from'));
        $this->assertArrayHasKey('has_more', $list->json('data'));

        $paginated = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/conversations/paginated?whatsapp_number=919999999999')
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertIsArray($paginated->json('data'));
        $this->assertIsArray($paginated->json('data.data'));
        $this->assertArrayHasKey('has_more', $paginated->json('data'));

        $dash = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'user_info' => ['uid', 'name', 'timezone', 'wallet_amount', 'wallet_balance'],
                    'wallet_info' => ['wallet_amount', 'wallet_balance'],
                    'subscription_info' => ['plan_name', 'remaining_days', 'valid_until'],
                    'stats' => ['today', 'last_7_days', 'last_30_days'],
                    'conversation_estimates' => [
                        'based_on_wallet_balance' => [
                            'daily_marketing',
                            'daily_utility',
                            'monthly_marketing',
                            'monthly_utility',
                            'service',
                        ],
                    ],
                    'list_growth' => ['available_lists', 'latest_list_stats'],
                    'recent_campaigns' => ['available_campaigns', 'latest_campaign_stats'],
                ],
            ]);
        // Flutter casts every top-level data value with Map.from — keep only maps.
        $dataKeys = array_keys($dash->json('data'));
        sort($dataKeys);
        $this->assertSame(
            ['conversation_estimates', 'list_growth', 'recent_campaigns', 'stats', 'subscription_info', 'user_info', 'wallet_info'],
            $dataKeys,
        );
        $this->assertIsArray($dash->json('data.list_growth.available_lists'));
        $this->assertSame('500.00', $dash->json('data.user_info.wallet_amount'));
        $this->assertSame('500.00', $dash->json('data.wallet_info.wallet_amount'));
        $today = $dash->json('data.stats.today');
        $this->assertSame(
            (int) $today['marketing'] + (int) $today['utility'],
            (int) $today['total_delivered'],
        );
        $estimates = $dash->json('data.conversation_estimates.based_on_wallet_balance');
        $this->assertArrayHasKey('service', $estimates);
        $this->assertGreaterThanOrEqual(0, (int) $estimates['service']);

        $walletTx = $this->withToken($this->accessToken)
            ->getJson('/api/v1/wallet-transactions')
            ->assertOk();
        // Exact legacy shape: { current_wallet_amount: { wallet_amount }, wallet_transactions: [] }
        $this->assertSame('500.00', $walletTx->json('current_wallet_amount.wallet_amount'));
        $this->assertIsArray($walletTx->json('wallet_transactions'));
        $this->assertArrayNotHasKey('data', $walletTx->json());

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

    public function test_open_conversation_returns_legacy_messages(): void
    {
        tenancy()->initialize($this->testTenant);

        $contact = Contact::factory()->create(['name' => 'Open Chat', 'phone' => '918888888802']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'contact_name' => $contact->name,
            'last_message_at' => now(),
        ]);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Customer hello',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Agent reply',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Sent,
        ]);

        $conversationId = (int) $conversation->id;
        tenancy()->end();

        $open = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/conversation?whatsapp_number=919999999999&conversation_id='.$conversationId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertIsArray($open->json('data.msgs'));
        $this->assertNotEmpty($open->json('data.msgs'));
        $this->assertSame('frnd', $open->json('data.msgs.0.type'));
        $this->assertSame('Customer hello', $open->json('data.msgs.0.msg'));
        $this->assertSame('my', $open->json('data.msgs.1.type'));

        $sub = $this->withToken($this->accessToken)
            ->getJson('/api/v1/mobile/inbox/conversations/sub-reply/'.$conversationId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertIsArray($sub->json('data.conversations'));
        $this->assertSame('Customer hello', $sub->json('data.conversations.0.msg'));
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
