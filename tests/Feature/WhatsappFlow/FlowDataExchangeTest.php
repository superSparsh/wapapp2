<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsappFlow;

use App\Enums\WhatsappFlowStatus;
use App\Enums\WhatsappFlowSubmitAction;
use App\Events\WhatsappFlowSubmitted;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class FlowDataExchangeTest extends TestCase
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
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function createFlowWithEndpoint(): WhatsappFlow
    {
        $flow = WhatsappFlow::factory()->active()->withFlowJson(2, 2)->create([
            'on_submit_action' => WhatsappFlowSubmitAction::None,
        ]);

        return $flow;
    }

    private function extractToken(WhatsappFlow $flow): string
    {
        $url = $flow->data_exchange_endpoint;
        $parts = explode('/', $url);

        return end($parts);
    }

    public function test_valid_submission_stores_data(): void
    {
        Event::fake([WhatsappFlowSubmitted::class]);
        $flow = $this->createFlowWithEndpoint();
        $token = $this->extractToken($flow);

        $response = $this->postJson(route('flow.exchange', $token), [
            'phone_number' => '+919876543210',
            'data' => ['full_name' => 'Test User', 'email' => 'test@test.com'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('whatsapp_flow_submissions', [
            'whatsapp_flow_id' => $flow->id,
            'contact_phone' => '+919876543210',
        ]);
        Event::assertDispatched(WhatsappFlowSubmitted::class);
    }

    public function test_invalid_flow_token_returns_404(): void
    {
        $this->postJson('/v1/flow-exchange/nonexistent-token', [
            'phone_number' => '+919999999999',
            'data' => ['name' => 'Test'],
        ])->assertStatus(404);
    }

    public function test_empty_payload_returns_422(): void
    {
        $flow = $this->createFlowWithEndpoint();
        $token = $this->extractToken($flow);

        $this->postJson(route('flow.exchange', $token), [])
            ->assertStatus(422);
    }

    public function test_idempotency_prevents_duplicate_submissions(): void
    {
        Event::fake([WhatsappFlowSubmitted::class]);
        $flow = $this->createFlowWithEndpoint();
        $token = $this->extractToken($flow);
        $payload = ['phone_number' => '+919876543210', 'data' => ['name' => 'Test']];

        $this->postJson(route('flow.exchange', $token), $payload);
        $this->postJson(route('flow.exchange', $token), $payload);

        $this->assertDatabaseCount('whatsapp_flow_submissions', 1);
    }

    public function test_screen_response_returns_success_on_final_screen(): void
    {
        Event::fake([WhatsappFlowSubmitted::class]);
        $flow = $this->createFlowWithEndpoint();
        $token = $this->extractToken($flow);

        $response = $this->postJson(route('flow.exchange', $token), [
            'phone_number' => '+919999999999',
            'action' => 'data_exchange',
            'screen' => 'screen_2',
            'data' => ['name' => 'Test'],
        ]);

        $response->assertOk();
        $response->assertJson(['screen' => 'SUCCESS']);
    }

    public function test_screen_response_returns_next_screen(): void
    {
        Event::fake([WhatsappFlowSubmitted::class]);
        $flow = $this->createFlowWithEndpoint();
        $token = $this->extractToken($flow);

        $response = $this->postJson(route('flow.exchange', $token), [
            'phone_number' => '+919999999998',
            'action' => 'data_exchange',
            'screen' => 'screen_1',
            'data' => ['name' => 'Test'],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['screen', 'data']);
    }

    public function test_webhook_action_sends_http_request(): void
    {
        Event::fake([WhatsappFlowSubmitted::class]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $flow = WhatsappFlow::factory()->active()->withFlowJson()->create([
            'on_submit_action' => WhatsappFlowSubmitAction::Webhook,
            'on_submit_webhook_url' => 'https://webhook.test/callback',
        ]);
        $token = $this->extractToken($flow);

        $this->postJson(route('flow.exchange', $token), [
            'phone_number' => '+919999999997',
            'data' => ['name' => 'Test'],
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.test/callback'
                && $request['event'] === 'whatsapp_flow_submission';
        });
    }

    public function test_submission_status_is_processed_after_success(): void
    {
        Event::fake([WhatsappFlowSubmitted::class]);
        $flow = $this->createFlowWithEndpoint();
        $token = $this->extractToken($flow);

        $this->postJson(route('flow.exchange', $token), [
            'phone_number' => '+919999999996',
            'data' => ['name' => 'Test'],
        ]);

        $submission = WhatsappFlowSubmission::query()->where('whatsapp_flow_id', $flow->id)->first();
        $this->assertSame('processed', $submission->status);
    }
}
