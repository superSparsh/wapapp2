<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Handlers\DeliveryStatusHandler;
use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\FormSubmission;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Models\SignupForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DeliveryStatusFormSubmissionSyncTest extends TestCase
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

    public function test_status_webhook_updates_form_submission_to_delivered_and_read(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = SignupForm::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'submission_count' => 1,
            'sent_count' => 1,
            'delivered_count' => 0,
            'read_count' => 0,
        ]);

        $contact = Contact::factory()->create(['phone' => '919876543210']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);

        $externalId = 'wamid.FORM-STATUS-001';
        $submission = FormSubmission::query()->create([
            'signup_form_id' => $form->id,
            'contact_id' => $contact->id,
            'phone' => '919876543210',
            'submission_data' => ['phone' => '919876543210'],
            'message_status' => 'sent',
            'external_message_id' => $externalId,
            'sent_at' => now(),
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Form template',
            'direction' => 'outbound',
            'message_type' => 'template',
            'status' => MessageStatus::Sent,
            'external_message_id' => $externalId,
            'sent_at' => now(),
            'metadata' => [
                'form_submission_id' => $submission->id,
                'signup_form_id' => $form->id,
                'wallet_source' => 'form_builder',
            ],
        ]);

        $submission->forceFill([
            'outbound_message_id' => $message->id,
        ])->save();

        app(\App\Domains\Webhooks\Services\WhatsappLineRegistryService::class)
            ->indexMessage($this->testTenant->id, $externalId, (int) $message->id);

        tenancy()->end();

        $deliveredEvent = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Delivered',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Delivered',
                'To' => '919876543210',
                'From' => (string) $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($deliveredEvent);

        tenancy()->initialize($this->testTenant);
        $submission->refresh();
        $form->refresh();

        $this->assertSame('delivered', $submission->message_status);
        $this->assertNotNull($submission->delivered_at);
        $this->assertSame(1, (int) $form->delivered_count);

        tenancy()->end();

        $readEvent = InboundWebhookEvent::query()->create([
            'event_type' => InboundWebhookEventType::Status,
            'idempotency_key' => $externalId.':Read',
            'payload' => [[
                'MessageId' => $externalId,
                'Status' => 'Read',
                'To' => '919876543210',
                'From' => (string) $this->testLine->phone,
            ]],
            'headers' => [],
            'status' => InboundWebhookStatus::Received,
            'retry_count' => 0,
            'created_at' => now(),
        ]);

        app(DeliveryStatusHandler::class)->handle($readEvent);

        tenancy()->initialize($this->testTenant);
        $submission->refresh();
        $form->refresh();

        $this->assertSame('read', $submission->message_status);
        $this->assertNotNull($submission->read_at);
        $this->assertNotNull($submission->delivered_at);
        $this->assertSame(1, (int) $form->delivered_count);
        $this->assertSame(1, (int) $form->read_count);
        $this->assertSame(1, (int) $form->sent_count);
    }
}
