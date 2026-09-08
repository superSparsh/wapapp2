<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeliveryStatusCampaignSyncTest extends TestCase
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

    public function test_status_webhook_updates_recipient_stored_with_local_message_id(): void
    {
        tenancy()->initialize($this->testTenant);

        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'status' => CampaignStatus::Sending,
            'total_recipients' => 1,
            'total_delivered' => 1,
            'total_read' => 0,
        ]);

        $contact = Contact::factory()->create(['phone' => '917018107871']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $externalId = '2026081252176165010296833';
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Campaign template',
            'direction' => 'outbound',
            'message_type' => 'template',
            'status' => MessageStatus::Sent,
            'external_message_id' => $externalId,
            'sent_at' => now(),
        ]);

        $recipient = CampaignRecipient::factory()->sent()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => '7018107871',
            'message_id' => (string) $message->id,
            'sent_at' => $message->sent_at,
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, $externalId, (int) $message->id);

        tenancy()->end();

        $event = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Read',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Read',
                'To' => '917018107871',
                'From' => '919999999999',
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($event);

        tenancy()->initialize($this->testTenant);

        $recipient->refresh();
        $campaign->refresh();
        $message->refresh();

        $this->assertSame(CampaignRecipientStatus::Read, $recipient->status);
        $this->assertSame($externalId, $recipient->message_id);
        $this->assertNotNull($recipient->read_at);
        $this->assertNotNull($recipient->delivered_at);
        $this->assertSame(1, (int) $campaign->total_read);
        $this->assertSame(MessageStatus::Read, $message->status);
    }

    public function test_status_webhook_matches_recipient_by_phone_when_local_id_is_stale(): void
    {
        tenancy()->initialize($this->testTenant);

        $campaign = Campaign::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'status' => CampaignStatus::Sending,
            'total_recipients' => 1,
            'total_delivered' => 1,
            'total_read' => 0,
        ]);

        $contact = Contact::factory()->create(['phone' => '917018107871']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $sentAt = now()->subMinute();
        $externalId = '2026081252156859828588544';
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Campaign template',
            'direction' => 'outbound',
            'message_type' => 'template',
            'status' => MessageStatus::Sent,
            'external_message_id' => $externalId,
            'sent_at' => $sentAt,
        ]);

        // Off-by-one style stale local id (real production mismatch).
        $recipient = CampaignRecipient::factory()->sent()->create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'contact_phone' => '7018107871',
            'message_id' => (string) ($message->id + 1),
            'sent_at' => $sentAt,
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, $externalId, (int) $message->id);

        tenancy()->end();

        $event = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Delivered',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Delivered',
                'To' => '917018107871',
                'From' => '919999999999',
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($event);

        tenancy()->initialize($this->testTenant);

        $recipient->refresh();
        $this->assertSame(CampaignRecipientStatus::Delivered, $recipient->status);
        $this->assertSame($externalId, $recipient->message_id);
        $this->assertNotNull($recipient->delivered_at);
    }
}
