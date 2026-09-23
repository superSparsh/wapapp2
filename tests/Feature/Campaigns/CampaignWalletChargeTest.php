<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Enums\WalletTransactionType;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\CountryPricing;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\Template;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class CampaignWalletChargeTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

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
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_wallet_debit_updates_balance_and_history(): void
    {
        tenancy()->initialize($this->testTenant);

        WalletAccount::query()->firstOrCreate([], ['balance' => 50, 'currency' => 'INR']);

        $txn = app(WalletService::class)->debit(
            amount: 1.25,
            description: 'Test debit',
            metadata: ['legacy_category' => 'MARKETING'],
            idempotencyKey: 'test-debit-1',
        );

        $this->assertSame(WalletTransactionType::Debit, $txn->type);
        $this->assertEquals(1.25, (float) $txn->amount);
        $this->assertEquals(48.75, (float) app(WalletService::class)->balance());

        $again = app(WalletService::class)->debit(
            amount: 1.25,
            description: 'Test debit',
            idempotencyKey: 'test-debit-1',
        );

        $this->assertSame($txn->id, $again->id);
        $this->assertEquals(48.75, (float) app(WalletService::class)->balance());
        $this->assertSame(1, WalletTransaction::query()->where('type', WalletTransactionType::Debit)->count());
    }

    public function test_delivery_webhook_charges_wallet_once_using_admin_rates(): void
    {
        tenancy()->initialize($this->testTenant);

        WalletAccount::query()->firstOrCreate([], ['balance' => 20, 'currency' => 'INR']);

        $template = Template::factory()->create([
            'category' => 'MARKETING',
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Sending,
            'template_id' => $template->id,
            'whatsapp_line_id' => $this->testLine->id,
            'name' => 'Billing Campaign',
        ]);

        $contact = Contact::factory()->create(['phone' => '919876543210']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_phone' => '919876543210',
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Template,
            'status' => MessageStatus::Sent,
            'body' => 'Hello',
            'external_message_id' => 'wamid.BILL-001',
            'metadata' => [
                'campaign_id' => $campaign->id,
                'template_category' => 'MARKETING',
                'billable' => true,
            ],
            'sent_at' => now(),
        ]);

        CampaignRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => '919876543210',
            'status' => CampaignRecipientStatus::Sent,
            'message_id' => 'wamid.BILL-001',
            'sent_at' => now(),
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, 'wamid.BILL-001', (int) $message->id);

        tenancy()->end();

        $event = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => 'bill-status-1',
            'payload' => [[
                'MessageId' => 'wamid.BILL-001',
                'Status' => 'Delivered',
                'To' => '919876543210',
                'From' => $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($event);

        tenancy()->initialize($this->testTenant);

        $this->assertEquals(19.0, (float) app(WalletService::class)->balance()); // 20 - (0.01*100)
        $this->assertSame(1, WalletTransaction::query()->where('type', WalletTransactionType::Debit)->count());

        $debit = WalletTransaction::query()->where('type', WalletTransactionType::Debit)->first();
        $this->assertEquals(1.0, (float) $debit->amount);
        $this->assertSame(Campaign::class, $debit->reference_type);
        $this->assertSame((int) $campaign->id, (int) $debit->reference_id);
        $this->assertSame('MARKETING', $debit->metadata['template_category'] ?? null);

        tenancy()->end();

        // Idempotent: second delivered status must not double-charge.
        $event2 = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => 'bill-status-2',
            'payload' => [[
                'MessageId' => 'wamid.BILL-001',
                'Status' => 'Read',
                'To' => '919876543210',
                'From' => $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($event2);

        tenancy()->initialize($this->testTenant);

        $this->assertEquals(19.0, (float) app(WalletService::class)->balance());
        $this->assertSame(1, WalletTransaction::query()->where('type', WalletTransactionType::Debit)->count());
    }

    public function test_charge_service_skips_non_campaign_messages(): void
    {
        tenancy()->initialize($this->testTenant);
        WalletAccount::query()->firstOrCreate([], ['balance' => 10, 'currency' => 'INR']);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Text,
            'status' => MessageStatus::Delivered,
            'body' => 'Hi',
            'external_message_id' => 'wamid.FREE-001',
            'metadata' => [],
            'delivered_at' => now(),
        ]);

        $result = app(\App\Domains\Billing\Services\TemplateWalletChargeService::class)
            ->chargeIfDelivered($message, 'Delivered');

        $this->assertNull($result);
        $this->assertEquals(10.0, (float) app(WalletService::class)->balance());
    }

    public function test_inbox_template_delivery_is_charged(): void
    {
        tenancy()->initialize($this->testTenant);
        WalletAccount::query()->firstOrCreate([], ['balance' => 10, 'currency' => 'INR']);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_phone' => '919999887766',
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Template,
            'status' => MessageStatus::Sent,
            'body' => 'Hello',
            'external_message_id' => 'wamid.INBOX-001',
            'metadata' => [
                'billable' => true,
                'wallet_source' => 'inbox',
                'template_category' => 'MARKETING',
            ],
            'sent_at' => now(),
        ]);

        $txn = app(\App\Domains\Billing\Services\TemplateWalletChargeService::class)
            ->chargeIfDelivered($message, 'Delivered');

        $this->assertNotNull($txn);
        $this->assertEquals(1.0, (float) $txn->amount);
        $this->assertEquals(9.0, (float) app(WalletService::class)->balance());
        $this->assertSame('inbox', $txn->metadata['wallet_source'] ?? null);
    }

    public function test_utility_second_message_within_24h_is_not_charged(): void
    {
        tenancy()->initialize($this->testTenant);
        WalletAccount::query()->firstOrCreate([], ['balance' => 10, 'currency' => 'INR']);

        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $first = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Template,
            'status' => MessageStatus::Delivered,
            'body' => 'Utility 1',
            'external_message_id' => 'wamid.UTIL-1',
            'metadata' => [
                'billable' => true,
                'wallet_source' => 'inbox',
                'template_category' => 'UTILITY',
            ],
        ]);

        app(\App\Domains\Billing\Services\TemplateWalletChargeService::class)
            ->chargeIfDelivered($first, 'Delivered');

        $second = Message::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Template,
            'status' => MessageStatus::Delivered,
            'body' => 'Utility 2',
            'external_message_id' => 'wamid.UTIL-2',
            'metadata' => [
                'billable' => true,
                'wallet_source' => 'inbox',
                'template_category' => 'UTILITY',
            ],
        ]);

        $txn = app(\App\Domains\Billing\Services\TemplateWalletChargeService::class)
            ->chargeIfDelivered($second, 'Delivered');

        $this->assertNull($txn);
        // Only first utility charged: 10 - (0.005 * 100) = 9.5
        $this->assertEquals(9.5, (float) app(WalletService::class)->balance());
    }
}
