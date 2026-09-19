<?php

namespace Tests\Feature\Webhooks;

use App\Domains\Webhooks\Jobs\DispatchOutboundWebhookJob;
use App\Domains\Webhooks\Listeners\NewLeadWebhookListener;
use App\Domains\Webhooks\Services\WebhookDeliveryService;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookSubscriptionStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WebhookDispatchTest extends TestCase
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

    public function test_dispatch_sends_post_with_signature_header(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $sub = WebhookSubscription::factory()->active()->create();
        $service = app(WebhookDeliveryService::class);

        $payload = ['event' => 'new_lead', 'data' => ['name' => 'Test']];
        $service->dispatch($sub, 'new_lead', $payload);

        Http::assertSent(function ($request) use ($sub) {
            return $request->hasHeader('X-Webhook-Signature')
                && $request->hasHeader('X-Webhook-Event', 'new_lead')
                && $request->hasHeader('X-Webhook-Delivery');
        });
    }

    public function test_dispatch_records_delivery_on_success(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $sub = WebhookSubscription::factory()->active()->create();
        $service = app(WebhookDeliveryService::class);

        $delivery = $service->dispatch($sub, 'new_lead', ['event' => 'new_lead']);

        $this->assertSame(WebhookDeliveryStatus::Sent, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertNotNull($delivery->duration_ms);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->error_message);
    }

    public function test_dispatch_records_failure_on_non_2xx(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => Http::response('Internal Server Error', 500)]);

        $sub = WebhookSubscription::factory()->active()->create();
        $service = app(WebhookDeliveryService::class);

        $delivery = $service->dispatch($sub, 'new_lead', ['event' => 'new_lead']);

        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertSame(500, $delivery->response_status);
        $this->assertSame('HTTP 500', $delivery->error_message);
    }

    public function test_dispatch_handles_timeout(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out')]);

        $sub = WebhookSubscription::factory()->active()->create();
        $service = app(WebhookDeliveryService::class);

        $delivery = $service->dispatch($sub, 'new_lead', ['event' => 'new_lead']);

        $this->assertSame(WebhookDeliveryStatus::Failed, $delivery->status);
        $this->assertNotNull($delivery->error_message);
    }

    public function test_new_lead_listener_dispatches_to_matching_subscriptions(): void
    {
        tenancy()->initialize($this->testTenant);

        Queue::fake();

        // Create 2 active subscriptions with new_lead event (bound to line / null = default)
        WebhookSubscription::factory()->count(2)->active()->create([
            'events' => ['new_lead'],
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $contact = Contact::factory()->create(['phone' => '917777777777', 'name' => 'Lead User']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'contact_name' => 'Lead User',
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'I am interested',
            'direction' => 'inbound',
            'message_type' => 'text',
            'status' => \App\Enums\MessageStatus::Delivered,
        ]);

        $listener = app(NewLeadWebhookListener::class);
        $listener->handle($message, $conversation);

        Queue::assertPushed(DispatchOutboundWebhookJob::class, 2);

        // Verify payload structure (legacy-enriched)
        Queue::assertPushed(DispatchOutboundWebhookJob::class, function ($job) use ($message) {
            return $job->eventType === 'new_lead'
                && $job->payload['event'] === 'new_lead'
                && $job->payload['data']['name'] === 'Lead User'
                && $job->payload['data']['phone'] === '7777777777'
                && $job->payload['data']['phone_e164'] === '917777777777'
                && $job->payload['data']['country_code'] === '+91'
                && $job->payload['data']['message'] === 'I am interested'
                && isset($job->payload['data']['business_line_id'])
                && isset($job->payload['data']['business_line']);
        });
    }

    public function test_new_lead_listener_skips_repeat_inbound_on_same_conversation(): void
    {
        tenancy()->initialize($this->testTenant);

        Queue::fake();

        WebhookSubscription::factory()->active()->create([
            'events' => ['new_lead'],
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $contact = Contact::factory()->create(['phone' => '916666666666']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'line_phone' => $this->testLine->phone,
        ]);

        $first = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'First',
            'direction' => 'inbound',
            'message_type' => 'text',
            'status' => \App\Enums\MessageStatus::Delivered,
        ]);

        $listener = app(NewLeadWebhookListener::class);
        $listener->handle($first, $conversation);
        Queue::assertPushed(DispatchOutboundWebhookJob::class, 1);

        $second = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Second',
            'direction' => 'inbound',
            'message_type' => 'text',
            'status' => \App\Enums\MessageStatus::Delivered,
        ]);

        Queue::fake();
        $listener->handle($second, $conversation);
        Queue::assertNothingPushed();
    }

    public function test_only_active_subscriptions_receive_webhooks(): void
    {
        tenancy()->initialize($this->testTenant);

        Queue::fake();

        $activeSub = WebhookSubscription::factory()->active()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);
        $inactiveSub = WebhookSubscription::factory()->inactive()->create([
            'whatsapp_line_id' => $this->testLine->id,
        ]);

        $contact = Contact::factory()->create(['phone' => '918888888888']);
        $conversation = Conversation::factory()->create([
            'whatsapp_line_id' => $this->testLine->id,
            'contact_id' => $contact->id,
            'contact_phone' => $contact->phone,
            'contact_name' => 'Test',
            'line_phone' => $this->testLine->phone,
            'last_message_at' => now(),
        ]);
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'body' => 'Hello',
            'direction' => 'inbound',
            'message_type' => 'text',
            'status' => \App\Enums\MessageStatus::Delivered,
        ]);

        $listener = app(NewLeadWebhookListener::class);
        $listener->handle($message, $conversation);

        Queue::assertPushed(DispatchOutboundWebhookJob::class, function ($job) use ($activeSub) {
            return $job->subscriptionId === $activeSub->id;
        });

        Queue::assertNotPushed(DispatchOutboundWebhookJob::class, function ($job) use ($inactiveSub) {
            return $job->subscriptionId === $inactiveSub->id;
        });
    }

    public function test_dispatch_outbound_webhook_job_calls_service(): void
    {
        tenancy()->initialize($this->testTenant);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $sub = WebhookSubscription::factory()->active()->create();

        $job = new DispatchOutboundWebhookJob(
            subscriptionId: (int) $sub->id,
            eventType: 'new_lead',
            payload: ['event' => 'new_lead', 'data' => ['name' => 'Job Test']],
        );

        $job->handle(app(WebhookDeliveryService::class));

        $delivery = WebhookDelivery::query()->first();
        $this->assertNotNull($delivery);
        $this->assertSame(WebhookDeliveryStatus::Sent, $delivery->status);
    }

    public function test_dispatch_outbound_webhook_job_handles_missing_subscription(): void
    {
        tenancy()->initialize($this->testTenant);

        $job = new DispatchOutboundWebhookJob(
            subscriptionId: 99999,
            eventType: 'new_lead',
            payload: ['event' => 'new_lead'],
        );

        // Should not throw — just return silently
        $job->handle(app(WebhookDeliveryService::class));

        $this->assertSame(0, WebhookDelivery::query()->count());
    }
}
