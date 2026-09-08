<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Domains\Webhooks\Handlers\InboundMessageHandler;
use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Enums\InboundWebhookStatus;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();

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
        $this->assertSame(InboundWebhookStatus::Received, $event->status);

        $job = new ProcessInboundWebhookJob((int) $event->id);
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

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
        (new ProcessInboundWebhookJob((int) $event->id))->handle(
            app(InboundMessageHandler::class),
            app(DeliveryStatusHandler::class),
        );

        $this->call('POST', route('webhooks.alibaba.message'), server: ['CONTENT_TYPE' => 'application/json'], content: $payload);

        $this->assertSame(1, InboundWebhookEvent::query()->count());

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, Message::query()->where('external_message_id', 'wamid.TEST-INBOUND-DUP')->count());
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

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, 'wamid.TEST-STATUS-001', $message->id);

        tenancy()->end();

        $payload = json_encode([[
            'MessageId' => 'wamid.TEST-STATUS-001',
            'Status' => 'Delivered',
        ]], JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.alibaba.status'), server: ['CONTENT_TYPE' => 'application/json'], content: $payload);
        $event = InboundWebhookEvent::query()->latest('id')->firstOrFail();

        (new ProcessInboundWebhookJob((int) $event->id))->handle(
            app(InboundMessageHandler::class),
            app(DeliveryStatusHandler::class),
        );

        tenancy()->initialize($this->testTenant);

        $message->refresh();
        $this->assertSame(MessageStatus::Delivered, $message->status);
        $this->assertNotNull($message->delivered_at);
    }
}
