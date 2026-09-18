<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Enums\InboundWebhookStatus;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboundWebhookTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_message_webhook_records_event_and_processes_into_inbox(): void
    {
        $payload = json_encode([[
            'MessageId' => 'wamid.TEST-INBOUND-001',
            'From' => '918888888801',
            'To' => '919999999999',
            'Name' => 'Webhook User',
            'Message' => 'Hello from webhook',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )->assertOk()->assertJson(['code' => 0]);

        $event = InboundWebhookEvent::query()->first();
        $this->assertNotNull($event);
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        tenancy()->initialize($this->testTenant);

        $this->assertDatabaseHas('contacts', ['phone' => '918888888801']);
        $this->assertDatabaseHas('messages', [
            'body' => 'Hello from webhook',
            'external_message_id' => 'wamid.TEST-INBOUND-001',
        ]);

        $conversation = Conversation::query()->first();
        $this->assertNotNull($conversation);
        $this->assertSame(1, $conversation->unread_count);
    }

    public function test_camel_case_message_webhook_processes_into_inbox(): void
    {
        $payload = json_encode([[
            'messageId' => 'wamid.TEST-CAMEL-001',
            'from' => '918888888804',
            'to' => '919999999999',
            'name' => 'Camel User',
            'message' => 'Hello camel case',
            'type' => 'text',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )->assertOk();

        $event = InboundWebhookEvent::query()->firstOrFail();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        tenancy()->initialize($this->testTenant);

        $this->assertDatabaseHas('messages', [
            'body' => 'Hello camel case',
            'external_message_id' => 'wamid.TEST-CAMEL-001',
        ]);

        $conversation = Conversation::query()->first();
        $this->assertNotNull($conversation);
        $this->assertSame(1, $conversation->unread_count);
    }

    public function test_wrapped_data_envelope_message_webhook_processes_into_inbox(): void
    {
        $payload = json_encode([
            'code' => 0,
            'data' => [[
                'MessageId' => 'wamid.TEST-WRAP-001',
                'From' => '918888888805',
                'To' => '919999999999',
                'Message' => ['text' => ['body' => 'Hello wrapped']],
                'Type' => 'TEXT',
            ]],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )->assertOk();

        $event = InboundWebhookEvent::query()->firstOrFail();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        tenancy()->initialize($this->testTenant);

        $this->assertDatabaseHas('messages', [
            'body' => 'Hello wrapped',
            'external_message_id' => 'wamid.TEST-WRAP-001',
        ]);
    }

    public function test_duplicate_message_webhook_is_idempotent(): void
    {
        $payload = json_encode([[
            'MessageId' => 'wamid.TEST-INBOUND-DUP',
            'From' => '918888888802',
            'To' => '919999999999',
            'Message' => 'First',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.alibaba.message'), server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        $event = InboundWebhookEvent::query()->firstOrFail();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        $this->call('POST', route('webhooks.alibaba.message'), server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        $this->assertSame(1, InboundWebhookEvent::query()->count());

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, Message::query()->where('external_message_id', 'wamid.TEST-INBOUND-DUP')->count());
    }

    public function test_inbound_message_lands_on_existing_ten_digit_conversation(): void
    {
        tenancy()->initialize($this->testTenant);

        $contact = Contact::factory()->create([
            'phone' => '7018107871',
            'name' => 'Imported User',
        ]);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => '7018107871',
            'line_phone' => $this->testLine->phone,
            'contact_name' => 'Imported User',
            'last_message_at' => now()->subDay(),
        ]);

        tenancy()->end();

        $payload = json_encode([[
            'MessageId' => 'wamid.TEST-VARIANT-001',
            'From' => '917018107871',
            'To' => '919999999999',
            'Name' => 'Imported User',
            'Message' => 'Hello from imported chat',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )->assertOk();

        tenancy()->initialize($this->testTenant);

        $this->assertSame(1, Conversation::query()->count());
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Hello from imported chat',
            'external_message_id' => 'wamid.TEST-VARIANT-001',
        ]);

        $conversation->refresh();
        $this->assertSame('917018107871', $conversation->contact_phone);
    }

    public function test_inbound_resolves_when_from_and_to_are_swapped(): void
    {
        $payload = json_encode([[
            'MessageId' => 'wamid.TEST-SWAP-001',
            'From' => '919999999999',
            'To' => '918888888809',
            'Name' => 'Swapped User',
            'Message' => 'Hello swapped',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )->assertOk();

        $event = InboundWebhookEvent::query()->firstOrFail();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        tenancy()->initialize($this->testTenant);

        $this->assertDatabaseHas('messages', [
            'body' => 'Hello swapped',
            'external_message_id' => 'wamid.TEST-SWAP-001',
        ]);
        $this->assertDatabaseHas('conversations', [
            'contact_phone' => '918888888809',
        ]);
    }

    public function test_status_webhook_updates_delivery_timestamps(): void
    {
        tenancy()->initialize($this->testTenant);

        $contact = Contact::factory()->create(['phone' => '918888888803']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Outbound',
            'direction' => 'outbound',
            'message_type' => 'text',
            'status' => MessageStatus::Queued,
            'external_message_id' => 'wamid.TEST-STATUS-001',
        ]);

        app(WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, 'wamid.TEST-STATUS-001', $message->id);

        tenancy()->end();

        $payload = json_encode([[
            'MessageId' => 'wamid.TEST-STATUS-001',
            'Status' => 'Delivered',
        ]], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.alibaba.status'), server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        $event = InboundWebhookEvent::query()->latest('id')->firstOrFail();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        tenancy()->initialize($this->testTenant);

        $message->refresh();
        $this->assertSame(MessageStatus::Delivered, $message->status);
        $this->assertNotNull($message->delivered_at);
    }
}
