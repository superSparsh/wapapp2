<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Domains\Webhooks\Handlers\InboundMessageHandler;
use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ProcessInboundWebhookJobTest extends TestCase
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

    private function createEvent(array $overrides = []): InboundWebhookEvent
    {
        return InboundWebhookEvent::query()->create(array_merge([
            'event_type' => InboundWebhookEventType::Message,
            'idempotency_key' => 'test-'.uniqid(),
            'payload' => [[
                'MessageId' => 'wamid.JOB-'.uniqid(),
                'From' => '918888820001',
                'To' => '919999999999',
                'Message' => 'Job test',
                'Type' => 'TEXT',
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ], $overrides));
    }

    public function test_job_marks_event_as_processed_on_success(): void
    {
        $event = $this->createEvent();

        $job = new ProcessInboundWebhookJob((int) $event->id);
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

        $event->refresh();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->error_message);
    }

    public function test_job_skips_already_processed_events(): void
    {
        $event = $this->createEvent([
            'status' => InboundWebhookStatus::Processed,
            'processed_at' => now(),
        ]);

        $originalRetryCount = $event->retry_count;

        $job = new ProcessInboundWebhookJob((int) $event->id);
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

        $event->refresh();
        // Should not increment retry_count or change status
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);
        $this->assertSame($originalRetryCount, $event->retry_count);
    }

    public function test_job_skips_duplicate_events(): void
    {
        $event = $this->createEvent([
            'status' => InboundWebhookStatus::Duplicate,
        ]);

        $job = new ProcessInboundWebhookJob((int) $event->id);
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

        $event->refresh();
        $this->assertSame(InboundWebhookStatus::Duplicate, $event->status);
    }

    public function test_job_marks_event_as_failed_on_exception(): void
    {
        // Create event with payload that will cause handler to throw (missing From/To/MessageId)
        $event = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Message,
            'idempotency_key' => 'test-fail-'.uniqid(),
            'payload' => [['Type' => 'TEXT']],  // Missing From, To, MessageId
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        $job = new ProcessInboundWebhookJob((int) $event->id);

        try {
            $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));
        } catch (\Throwable) {
            // Expected — handler throws RuntimeException
        }

        $event->refresh();
        $this->assertSame(InboundWebhookStatus::Failed, $event->status);
        $this->assertNotNull($event->error_message);
    }

    public function test_job_increments_retry_count(): void
    {
        $event = $this->createEvent();
        $this->assertSame(0, $event->retry_count);

        $job = new ProcessInboundWebhookJob((int) $event->id);
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

        $event->refresh();
        $this->assertSame(1, $event->retry_count);
    }

    public function test_job_returns_silently_for_missing_event(): void
    {
        $job = new ProcessInboundWebhookJob(999999);

        // Should not throw — just return silently
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

        $this->assertTrue(true); // Reached here without exception
    }

    public function test_job_routes_message_type_to_message_handler(): void
    {
        $event = $this->createEvent([
            'event_type' => InboundWebhookEventType::Message,
        ]);

        $job = new ProcessInboundWebhookJob((int) $event->id);
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

        $event->refresh();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        // Verify the message was recorded in tenant DB
        tenancy()->initialize($this->testTenant);
        $this->assertDatabaseHas('messages', [
            'external_message_id' => $event->payload[0]['MessageId'],
        ]);
    }

    public function test_job_routes_status_type_to_status_handler(): void
    {
        // First create a message in tenant DB for the status handler to update
        tenancy()->initialize($this->testTenant);

        $contact = \App\Models\Contact::factory()->create(['phone' => '918888820099']);
        $conversation = \App\Models\Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);
        $message = \App\Models\Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Status job test',
            'direction' => 'outbound',
            'message_type' => 'text',
            'status' => \App\Enums\MessageStatus::Queued,
            'external_message_id' => 'wamid.JOB-STATUS-ROUTE',
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, 'wamid.JOB-STATUS-ROUTE', $message->id);

        tenancy()->end();

        $extMessageId = 'wamid.JOB-STATUS-ROUTE';

        $event = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $extMessageId.':Sent',
            'payload' => [['MessageId' => $extMessageId, 'Status' => 'Sent']],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        $job = new ProcessInboundWebhookJob((int) $event->id);
        $job->handle(app(InboundMessageHandler::class), app(DeliveryStatusHandler::class));

        $event->refresh();
        $this->assertSame(InboundWebhookStatus::Processed, $event->status);

        // Verify message status was updated
        tenancy()->initialize($this->testTenant);
        $message->refresh();
        $this->assertSame(\App\Enums\MessageStatus::Sent, $message->status);
        $this->assertNotNull($message->sent_at);
    }
}
