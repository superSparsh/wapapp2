<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Models\MessageExternalIndex;
use App\Models\WhatsappLineRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WebhookTenantResolverTest extends TestCase
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

    public function test_status_webhook_processes_when_central_index_missing_by_scanning_tenant_db(): void
    {
        tenancy()->initialize($this->testTenant);

        $contact = Contact::factory()->create(['phone' => '917018107871']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $externalId = '2026101267070518333800448';
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'API template',
            'direction' => 'outbound',
            'message_type' => 'template',
            'status' => MessageStatus::Sent,
            'external_message_id' => $externalId,
            'sent_at' => now(),
            'metadata' => [
                'wallet_source' => 'api',
                'billable' => true,
                'template_category' => 'MARKETING',
            ],
        ]);

        tenancy()->end();

        MessageExternalIndex::query()->where('external_message_id', $externalId)->delete();
        WhatsappLineRegistry::query()->delete();

        $this->assertSame(0, MessageExternalIndex::query()->where('external_message_id', $externalId)->count());

        $event = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Delivered',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Delivered',
                'To' => '917018107871',
                'From' => '918217312289',
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($event);

        $event->refresh();
        $this->assertSame(InboundWebhookStatus::Received, $event->status);
        $this->assertSame($this->testTenant->id, $event->tenant_id);

        $index = MessageExternalIndex::query()->where('external_message_id', $externalId)->first();
        $this->assertNotNull($index);
        $this->assertSame($this->testTenant->id, $index->tenant_id);

        tenancy()->initialize($this->testTenant);
        $message = Message::query()->where('external_message_id', $externalId)->firstOrFail();
        $this->assertSame(MessageStatus::Delivered, $message->status);
        tenancy()->end();
    }

    public function test_status_webhook_resolves_tenant_from_origin_phone_number(): void
    {
        tenancy()->initialize($this->testTenant);

        $externalId = '2026101267070518333800449';
        $message = Message::query()->create([
            'conversation_id' => Conversation::factory()->create([
                'whatsapp_line_id' => $this->testLine->id,
                'contact_phone' => '917018107872',
                'line_phone' => $this->testLine->phone,
                'last_message_at' => now(),
            ])->id,
            'body' => 'API template',
            'direction' => 'outbound',
            'message_type' => 'template',
            'status' => MessageStatus::Sent,
            'external_message_id' => $externalId,
            'sent_at' => now(),
        ]);

        tenancy()->end();

        MessageExternalIndex::query()->where('external_message_id', $externalId)->delete();

        $event = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Delivered',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Delivered',
                'To' => '917018107872',
                'OriginPhoneNumber' => $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($event);

        $event->refresh();
        $this->assertSame($this->testTenant->id, $event->tenant_id);

        tenancy()->initialize($this->testTenant);
        $message->refresh();
        $this->assertSame(MessageStatus::Delivered, $message->status);
        tenancy()->end();
    }
}
