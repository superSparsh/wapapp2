<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Services\InboundWebhookRecorder;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class InboundWebhookRecorderTest extends TestCase
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

    public function test_record_creates_event_with_received_status(): void
    {
        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-001',
            'From' => '918888810001',
            'To' => '919999999999',
            'Message' => 'Record test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $event = $recorder->record(
            eventType: InboundWebhookEventType::Message,
            rawBody: $payload,
            headers: ['content-type' => 'application/json'],
        );

        $this->assertSame(InboundWebhookStatus::Processed, $event->status);
        $this->assertSame('wamid.REC-001', $event->idempotency_key);
        $this->assertIsArray($event->payload);
    }

    public function test_record_processes_message_immediately(): void
    {
        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-002',
            'From' => '918888810002',
            'To' => '919999999999',
            'Message' => 'Dispatch test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $event = $recorder->record(
            eventType: InboundWebhookEventType::Message,
            rawBody: $payload,
        );

        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        tenancy()->initialize($this->testTenant);
        $this->assertDatabaseHas('messages', [
            'body' => 'Dispatch test',
            'external_message_id' => 'wamid.REC-002',
        ]);
    }

    public function test_duplicate_key_returns_existing_event_without_dispatching_job(): void
    {
        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-DUP',
            'From' => '918888810003',
            'To' => '919999999999',
            'Message' => 'Duplicate test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $first = $recorder->record(InboundWebhookEventType::Message, $payload);
        $second = $recorder->record(InboundWebhookEventType::Message, $payload);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(InboundWebhookStatus::Processed, $second->status);
    }

    public function test_duplicate_marks_event_as_duplicate_if_not_processed(): void
    {
        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-DUP2',
            'From' => '918888810004',
            'To' => '919999999999',
            'Message' => 'Dup mark test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $first = $recorder->record(InboundWebhookEventType::Message, $payload);
        $this->assertSame(InboundWebhookStatus::Processed, $first->status);

        $second = $recorder->record(InboundWebhookEventType::Message, $payload);

        $first->refresh();
        $this->assertSame(InboundWebhookStatus::Processed, $first->status);
        $this->assertSame($first->id, $second->id);
    }

    public function test_duplicate_does_not_overwrite_processed_status(): void
    {
        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-PROC',
            'From' => '918888810005',
            'To' => '919999999999',
            'Message' => 'Processed test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $first = $recorder->record(InboundWebhookEventType::Message, $payload);

        $first->forceFill(['status' => InboundWebhookStatus::Processed])->save();

        $recorder->record(InboundWebhookEventType::Message, $payload);

        $first->refresh();
        $this->assertSame(InboundWebhookStatus::Processed, $first->status);
    }

    public function test_fallback_idempotency_key_uses_sha256_hash(): void
    {
        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'From' => '918888810006',
            'To' => '919999999999',
            'Message' => 'No MessageId',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $event = $recorder->record(InboundWebhookEventType::Message, $payload);

        $expectedKey = 'hash:'.hash('sha256', $payload);
        $this->assertSame($expectedKey, $event->idempotency_key);
    }

    public function test_status_event_skips_sync_and_queues_when_oci_enabled(): void
    {
        config([
            'oci-workers.enabled' => true,
            'oci-workers.status_queue_only' => true,
            'oci-workers.queues.status' => 'status',
        ]);

        \Illuminate\Support\Facades\Queue::fake();

        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.STATUS-OCI-001',
            'Status' => '2',
            'Type' => 'MESSAGE_STATUS',
        ]], JSON_THROW_ON_ERROR);

        $event = $recorder->record(InboundWebhookEventType::Status, $payload);

        $this->assertSame(InboundWebhookStatus::Received, $event->status);

        \Illuminate\Support\Facades\Queue::assertPushedOn(
            'status',
            \App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob::class,
        );
    }

    public function test_status_event_syncs_immediately_for_api_direct_messages_even_when_oci_queue_only(): void
    {
        config([
            'oci-workers.enabled' => true,
            'oci-workers.status_queue_only' => true,
            'oci-workers.queues.status' => 'status',
            'whatsapp.outbound_driver' => 'local',
        ]);

        $contact = \App\Models\Contact::factory()->create(['phone' => '918888810099']);
        $conversation = \App\Models\Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $message = \App\Models\Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'API status fast path',
            'direction' => 'outbound',
            'message_type' => 'template',
            'status' => \App\Enums\MessageStatus::Sent,
            'external_message_id' => 'wamid.API-FAST-001',
            'sent_at' => now(),
            'metadata' => [
                'api_send' => true,
                'wallet_source' => 'api',
            ],
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, 'wamid.API-FAST-001', $message->id);

        tenancy()->end();

        $recorder = app(InboundWebhookRecorder::class);
        $payload = json_encode([[
            'MessageId' => 'wamid.API-FAST-001',
            'Status' => 'Delivered',
            'From' => $this->testLine->phone,
            'To' => $contact->phone,
        ]], JSON_THROW_ON_ERROR);

        $event = $recorder->record(InboundWebhookEventType::Status, $payload);

        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        tenancy()->initialize($this->testTenant);
        $message->refresh();
        $this->assertSame(\App\Enums\MessageStatus::Delivered, $message->status);
    }
}
