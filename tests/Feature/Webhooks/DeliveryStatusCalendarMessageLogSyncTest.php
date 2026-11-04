<?php

namespace Tests\Feature\Webhooks;

use App\Domains\ThirdParty\Enums\MessageLogStatus;
use App\Domains\ThirdParty\Models\CalendlyMessageLog;
use App\Domains\ThirdParty\Models\GoogleCalendarMessageLog;
use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeliveryStatusCalendarMessageLogSyncTest extends TestCase
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

    public function test_status_webhook_marks_calendly_and_google_logs_delivered(): void
    {
        tenancy()->initialize($this->testTenant);

        $calendlyExternalId = 'wamid.CALENDLY-DELIV-001';
        $googleExternalId = 'wamid.GOOGLE-DELIV-001';

        $calendlyLog = CalendlyMessageLog::query()->create([
            'user_id' => $this->testUser->id,
            'event_id' => 1,
            'recipient_type' => 'customer',
            'recipient_number' => '919876543210',
            'invitee_email' => 'invitee@example.com',
            'event_name' => 'Demo Call',
            'event_type' => 'created',
            'status' => MessageLogStatus::Sent,
            'external_message_id' => $calendlyExternalId,
            'sent_at' => now(),
        ]);

        $googleLog = GoogleCalendarMessageLog::query()->create([
            'user_id' => $this->testUser->id,
            'event_id' => 1,
            'recipient_type' => 'customer',
            'recipient_number' => '919876543210',
            'invitee_email' => 'invitee@example.com',
            'event_name' => 'Meet Sync',
            'event_type' => 'created',
            'status' => MessageLogStatus::Sent,
            'external_message_id' => $googleExternalId,
            'sent_at' => now(),
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, $calendlyExternalId);
        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, $googleExternalId);

        tenancy()->end();

        app(DeliveryStatusHandler::class)->handle(InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $calendlyExternalId.':Delivered',
            'payload' => [[
                'MessageId' => $calendlyExternalId,
                'Status' => 'Delivered',
                'To' => '919876543210',
                'From' => (string) $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]));

        app(DeliveryStatusHandler::class)->handle(InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $googleExternalId.':Delivered',
            'payload' => [[
                'MessageId' => $googleExternalId,
                'Status' => 'Delivered',
                'To' => '919876543210',
                'From' => (string) $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]));

        tenancy()->initialize($this->testTenant);
        $calendlyLog->refresh();
        $googleLog->refresh();

        $this->assertSame(MessageLogStatus::Delivered, $calendlyLog->status);
        $this->assertNotNull($calendlyLog->delivered_at);
        $this->assertSame(MessageLogStatus::Delivered, $googleLog->status);
        $this->assertNotNull($googleLog->delivered_at);
    }

    public function test_delivered_calendar_log_is_not_downgraded_to_sent(): void
    {
        tenancy()->initialize($this->testTenant);

        $externalId = 'wamid.CALENDLY-NO-DOWNGRADE';
        $log = CalendlyMessageLog::query()->create([
            'user_id' => $this->testUser->id,
            'event_id' => 2,
            'recipient_type' => 'customer',
            'recipient_number' => '919876543210',
            'invitee_email' => 'invitee@example.com',
            'event_name' => 'Demo Call',
            'event_type' => 'created',
            'status' => MessageLogStatus::Delivered,
            'external_message_id' => $externalId,
            'sent_at' => now()->subMinute(),
            'delivered_at' => now()->subMinute(),
        ]);

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, $externalId);

        tenancy()->end();

        app(DeliveryStatusHandler::class)->handle(InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Sent',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Sent',
                'To' => '919876543210',
                'From' => (string) $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]));

        tenancy()->initialize($this->testTenant);
        $log->refresh();

        $this->assertSame(MessageLogStatus::Delivered, $log->status);
        $this->assertNotNull($log->delivered_at);
    }
}
