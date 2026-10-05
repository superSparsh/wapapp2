<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Enums\MessageStatus;
use App\Enums\TenantUserAccountType;
use App\Models\CountryPricing;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Models\MessageExternalIndex;
use App\Models\PlatformSetting;
use App\Models\Template;
use App\Models\TenantUserAccess;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DirectMessageApiTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        config([
            'whatsapp.outbound_driver' => 'local',
            'whatsapp.alibaba.access_key_id' => null,
            'whatsapp.alibaba.access_key_secret' => null,
            'billing.service_free_messages_per_month' => 0,
        ]);

        PlatformSetting::query()->updateOrCreate(
            ['key' => 'wallet.conversion_price'],
            ['value' => '100'],
        );

        CountryPricing::query()->create([
            'country_code' => 'IN',
            'country_name' => 'India',
            'currency' => 'USD',
            'marketing_price' => 0.01,
            'utility_price' => 0.005,
            'status' => 1,
        ]);

        WalletAccount::query()->firstOrCreate([], [
            'balance' => 500,
            'currency' => 'INR',
        ]);

        $this->apiToken = Str::random(60);
        $this->testUser->forceFill(['api_token' => $this->apiToken])->save();

        tenancy()->central(function (): void {
            TenantUserAccess::query()->create([
                'email' => strtolower((string) $this->testUser->email),
                'phone' => $this->testUser->phone,
                'tenant_id' => $this->testTenant->id,
                'account_type' => TenantUserAccountType::Owner,
                'is_active' => true,
                'api_token' => $this->apiToken,
            ]);
        });
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_directmessage_requires_api_token(): void
    {
        $this->post('/api/v1/directmessage', [
            'template_uid' => 'x',
            'to' => '919876543210',
        ], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_directmessage_sends_template_and_returns_message_id(): void
    {
        $template = Template::factory()->create([
            'code' => '1125253687146348544',
            'language' => 'en',
            'category' => 'MARKETING',
        ]);

        $response = $this->post('/api/v1/directmessage', [
            'api_token' => $this->apiToken,
            'template_uid' => $template->uuid,
            'to' => '919876543210',
            'from' => $this->testLine->phone,
            'first_name' => 'Sparsh',
            'last_name' => 'Test',
            'variable_name' => [
                'offer' => '20% off',
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'sent')
            ->assertJsonStructure(['message_id', 'to', 'from']);

        $messageId = (string) $response->json('message_id');
        $message = Message::query()->where('uuid', $messageId)->first();

        $this->assertNotNull($message);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame('api', data_get($message->metadata, 'wallet_source'));
        $this->assertSame('API Direct Message', data_get($message->metadata, 'wallet_source_label'));
        $this->assertTrue((bool) data_get($message->metadata, 'billable'));
        $this->assertSame('20% off', data_get($message->metadata, 'template_params.offer'));

        $externalId = (string) $message->external_message_id;
        // Local outbound driver uses local_* ids and intentionally skips central indexing.
        if ($externalId !== '' && ! str_starts_with($externalId, 'local_')) {
            $index = MessageExternalIndex::query()
                ->where('external_message_id', $externalId)
                ->first();
            $this->assertNotNull($index);
            $this->assertSame($this->testTenant->id, $index->tenant_id);
            $this->assertSame($message->id, $index->message_id);
        }
    }

    public function test_directmessage_rejects_unapproved_template(): void
    {
        $template = Template::factory()->draft()->create();

        $this->post('/api/v1/directmessage', [
            'api_token' => $this->apiToken,
            'template_uid' => $template->uuid,
            'to' => '919876543210',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['template_uid']);
    }

    public function test_directmessage_rejects_unknown_from_number(): void
    {
        $template = Template::factory()->create([
            'code' => '1125253687146348545',
        ]);

        $this->post('/api/v1/directmessage', [
            'api_token' => $this->apiToken,
            'template_uid' => $template->uuid,
            'to' => '919876543210',
            'from' => '911111111111',
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    }

    public function test_getstatusofmessage_returns_current_status(): void
    {
        $template = Template::factory()->create([
            'code' => '1125253687146348546',
        ]);

        $send = $this->post('/api/v1/directmessage', [
            'api_token' => $this->apiToken,
            'template_uid' => $template->uuid,
            'to' => '919876543211',
        ], ['Accept' => 'application/json'])->assertOk();

        $messageId = (string) $send->json('message_id');

        $this->get('/api/v1/getstatusofmessage?'.http_build_query([
            'api_token' => $this->apiToken,
            'message_id' => $messageId,
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message_id', $messageId)
            ->assertJsonPath('status', 'sent');
    }

    public function test_delivered_api_message_charges_wallet_with_api_description(): void
    {
        $template = Template::factory()->create([
            'code' => '1125253687146348547',
            'category' => 'UTILITY',
        ]);

        $send = $this->post('/api/v1/directmessage', [
            'api_token' => $this->apiToken,
            'template_uid' => $template->uuid,
            'to' => '919876543212',
        ], ['Accept' => 'application/json'])->assertOk();

        $message = Message::query()->where('uuid', (string) $send->json('message_id'))->firstOrFail();
        $message->forceFill([
            'status' => MessageStatus::Delivered,
            'delivered_at' => now(),
        ])->save();

        // MessageObserver charges on Delivered; assert the resulting wallet row.
        $message->refresh();
        $this->assertTrue((bool) data_get($message->metadata, 'wallet_charged'));

        $tx = WalletTransaction::query()
            ->where('reference_type', Message::class)
            ->where('reference_id', $message->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($tx);
        $this->assertStringContainsString('API Direct Message', (string) $tx->description);
        $this->assertSame('api', data_get($tx->metadata, 'wallet_source'));
    }

    public function test_getstatusofmessage_returns_404_for_unknown_id(): void
    {
        $this->get('/api/v1/getstatusofmessage?'.http_build_query([
            'api_token' => $this->apiToken,
            'message_id' => (string) Str::uuid(),
        ]), ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    public function test_getstatusofmessage_syncs_pending_delivered_webhook_for_api_message(): void
    {
        $template = Template::factory()->create([
            'code' => '1125253687146348548',
            'category' => 'UTILITY',
        ]);

        $send = $this->post('/api/v1/directmessage', [
            'api_token' => $this->apiToken,
            'template_uid' => $template->uuid,
            'to' => '919876543213',
        ], ['Accept' => 'application/json'])->assertOk();

        $message = Message::query()->where('uuid', (string) $send->json('message_id'))->firstOrFail();
        $externalId = (string) $message->external_message_id;
        $this->assertNotSame('', $externalId);

        InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Delivered',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Delivered',
                'From' => $this->testLine->phone,
                'To' => '919876543213',
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        $this->get('/api/v1/getstatusofmessage?'.http_build_query([
            'api_token' => $this->apiToken,
            'message_id' => (string) $message->uuid,
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'delivered');

        $message->refresh();
        $this->assertSame(MessageStatus::Delivered, $message->status);
        $this->assertNotNull($message->delivered_at);

        $event = InboundWebhookEvent::query()
            ->where('idempotency_key', $externalId.':Delivered')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);
    }
}
