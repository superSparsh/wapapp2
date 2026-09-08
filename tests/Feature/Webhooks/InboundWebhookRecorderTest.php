<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Jobs\ProcessInboundWebhookJob;
use App\Domains\Webhooks\Services\InboundWebhookRecorder;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        Queue::fake();

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

        $this->assertSame(InboundWebhookStatus::Received, $event->status);
        $this->assertSame('wamid.REC-001', $event->idempotency_key);
        $this->assertIsArray($event->payload);
    }

    public function test_record_dispatches_job_on_configured_queue(): void
    {
        Queue::fake();

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

        Queue::assertPushed(ProcessInboundWebhookJob::class, function ($job) use ($event) {
            return $job->eventId === $event->id;
        });
    }

    public function test_duplicate_key_returns_existing_event_without_dispatching_job(): void
    {
        Queue::fake();

        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-DUP',
            'From' => '918888810003',
            'To' => '919999999999',
            'Message' => 'Duplicate test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        // First call — creates and dispatches
        $first = $recorder->record(InboundWebhookEventType::Message, $payload);
        Queue::assertPushed(ProcessInboundWebhookJob::class, 1);

        // Second call — should return same event, no new dispatch
        $second = $recorder->record(InboundWebhookEventType::Message, $payload);

        $this->assertSame($first->id, $second->id);
        // Still only 1 job dispatched (the first one)
        Queue::assertPushed(ProcessInboundWebhookJob::class, 1);
    }

    public function test_duplicate_marks_event_as_duplicate_if_not_processed(): void
    {
        Queue::fake();

        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-DUP2',
            'From' => '918888810004',
            'To' => '919999999999',
            'Message' => 'Dup mark test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $first = $recorder->record(InboundWebhookEventType::Message, $payload);
        $this->assertSame(InboundWebhookStatus::Received, $first->status);

        // Second call — should mark as duplicate
        $second = $recorder->record(InboundWebhookEventType::Message, $payload);

        $first->refresh();
        $this->assertSame(InboundWebhookStatus::Duplicate, $first->status);
    }

    public function test_duplicate_does_not_overwrite_processed_status(): void
    {
        Queue::fake();

        $recorder = app(InboundWebhookRecorder::class);

        $payload = json_encode([[
            'MessageId' => 'wamid.REC-PROC',
            'From' => '918888810005',
            'To' => '919999999999',
            'Message' => 'Processed test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $first = $recorder->record(InboundWebhookEventType::Message, $payload);

        // Simulate processing completed
        $first->forceFill(['status' => InboundWebhookStatus::Processed])->save();

        // Second call — should NOT overwrite processed status
        $recorder->record(InboundWebhookEventType::Message, $payload);

        $first->refresh();
        $this->assertSame(InboundWebhookStatus::Processed, $first->status);
    }

    public function test_fallback_idempotency_key_uses_sha256_hash(): void
    {
        Queue::fake();

        $recorder = app(InboundWebhookRecorder::class);

        // Payload without MessageId — should fall back to hash
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
}
