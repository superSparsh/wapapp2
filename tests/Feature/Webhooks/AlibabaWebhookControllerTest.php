<?php

namespace Tests\Feature\Webhooks;

use App\Enums\InboundWebhookEventType;
use App\Enums\InboundWebhookStatus;
use App\Models\InboundWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AlibabaWebhookControllerTest extends TestCase
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

    public function test_message_endpoint_returns_200_json(): void
    {
        Queue::fake();

        $payload = json_encode([[
            'MessageId' => 'wamid.CTRL-001',
            'From' => '918888800001',
            'To' => '919999999999',
            'Message' => 'Hello',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )
            ->assertOk()
            ->assertJson(['code' => 0, 'msg' => 'Success']);
    }

    public function test_status_endpoint_returns_200_json(): void
    {
        Queue::fake();

        $payload = json_encode([[
            'MessageId' => 'wamid.CTRL-STATUS-001',
            'Status' => 'Delivered',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.status'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        )
            ->assertOk()
            ->assertJson(['code' => 0, 'msg' => 'Success']);
    }

    public function test_alibaba_uplink_is_reachable_under_api_prefix(): void
    {
        Queue::fake();

        $messagePayload = json_encode([[
            'MessageId' => 'wamid.API-PATH-MSG',
            'From' => '918888800001',
            'To' => '919999999999',
            'Message' => 'Hello',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/message-uplink/alibaba',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $messagePayload,
        )
            ->assertOk()
            ->assertJson(['code' => 0, 'msg' => 'Success']);

        $statusPayload = json_encode([[
            'MessageId' => 'wamid.API-PATH-STATUS',
            'Status' => 'Delivered',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/status-uplink/alibaba',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $statusPayload,
        )
            ->assertOk()
            ->assertJson(['code' => 0, 'msg' => 'Success']);
    }

    public function test_message_endpoint_records_event_in_central_db(): void
    {
        Queue::fake();

        $payload = json_encode([[
            'MessageId' => 'wamid.CTRL-002',
            'From' => '918888800002',
            'To' => '919999999999',
            'Message' => 'Record test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );

        $event = InboundWebhookEvent::query()
            ->where('idempotency_key', 'wamid.CTRL-002')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(InboundWebhookEventType::Message, $event->event_type);
        $this->assertSame(InboundWebhookStatus::Received, $event->status);
    }

    public function test_status_endpoint_records_event_in_central_db(): void
    {
        Queue::fake();

        $payload = json_encode([[
            'MessageId' => 'wamid.CTRL-STATUS-002',
            'Status' => 'Read',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.status'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );

        $event = InboundWebhookEvent::query()
            ->where('idempotency_key', 'wamid.CTRL-STATUS-002:Read')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame(InboundWebhookEventType::Status, $event->event_type);
    }

    public function test_message_endpoint_handles_malformed_json(): void
    {
        Queue::fake();

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: 'this is not json',
        )
            ->assertOk()
            ->assertJson(['code' => 0]);

        // Should still record an event with raw fallback
        $event = InboundWebhookEvent::query()->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertArrayHasKey('raw', $event->payload);
    }

    public function test_get_request_returns_200(): void
    {
        Queue::fake();

        // Route::match(['get', 'post']) — GET should also work (legacy compatibility)
        $this->call(
            'GET',
            route('webhooks.alibaba.message'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '',
        )->assertOk();
    }

    public function test_event_stores_headers(): void
    {
        Queue::fake();

        $payload = json_encode([[
            'MessageId' => 'wamid.CTRL-HDR-001',
            'From' => '918888800003',
            'To' => '919999999999',
            'Message' => 'Header test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('webhooks.alibaba.message'),
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CUSTOM_HEADER' => 'custom-value',
            ],
            content: $payload,
        );

        $event = InboundWebhookEvent::query()
            ->where('idempotency_key', 'wamid.CTRL-HDR-001')
            ->first();

        $this->assertNotNull($event);
        $this->assertIsArray($event->headers);
        $this->assertNotEmpty($event->headers);
    }

    public function test_alibaba_uplink_accepts_burst_posts_for_cams_volume(): void
    {
        Queue::fake();

        $payload = json_encode([[
            'MessageId' => 'wamid.RATE-TEST',
            'From' => '918888800099',
            'To' => '919999999999',
            'Message' => 'Rate test',
            'Type' => 'TEXT',
        ]], JSON_THROW_ON_ERROR);

        // CAMS status callbacks can burst well past 60/min; do not 429 them.
        for ($i = 0; $i < 61; $i++) {
            $this->call(
                'POST',
                '/api/v1/message-uplink/alibaba',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: $payload,
            )->assertOk();
        }
    }
}
