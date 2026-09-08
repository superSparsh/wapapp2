<?php

declare(strict_types=1);

namespace Tests\Feature\Drip;

use App\Domains\Drip\Jobs\ExecuteDripStepJob;
use App\Domains\Drip\Services\DripFlowEngine;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\DripCampaign;
use App\Models\DripCampaignState;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class DripSkippedNodesTest extends TestCase
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

    public function test_media_message_creates_outbound_media_message(): void
    {
        $campaign = $this->campaignWithNodes([
            [
                'id' => 'media_1',
                'type' => 'mediaMessage',
                'data' => [
                    'label' => 'Send Image',
                    'media_url' => 'https://cdn.example.com/promo.jpg',
                    'media_type' => 'image',
                    'caption' => 'Hello',
                ],
            ],
        ]);

        $state = $this->activeState($campaign, 'media_1');
        app(DripFlowEngine::class)->executeFromState($state);

        $message = Message::query()->where('conversation_id', $state->conversation_id)->latest('id')->first();
        $this->assertNotNull($message);
        $this->assertSame(MessageType::Image, $message->message_type);
        $this->assertSame('https://cdn.example.com/promo.jpg', $message->metadata['media_url'] ?? null);
        $this->assertSame('Hello', $message->body);

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Completed, $state->status);
    }

    public function test_http_request_stores_response_in_state_variables(): void
    {
        Http::fake([
            'https://hooks.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $campaign = $this->campaignWithNodes([
            [
                'id' => 'http_1',
                'type' => 'httpRequest',
                'data' => [
                    'label' => 'Webhook',
                    'url' => 'https://hooks.example.com/drip',
                    'method' => 'GET',
                    'result_variable' => 'hook',
                ],
            ],
        ]);

        $state = $this->activeState($campaign, 'http_1');
        app(DripFlowEngine::class)->executeFromState($state);

        $state->refresh();
        $this->assertTrue((bool) ($state->variables['hook_success'] ?? false));
        $this->assertSame(200, (int) ($state->variables['hook_status'] ?? 0));
        $this->assertNotNull($state->variables['hook'] ?? null);
        Http::assertSentCount(1);
    }

    public function test_typing_indicator_schedules_delayed_continue(): void
    {
        Queue::fake();

        $campaign = $this->campaignWithNodes([
            [
                'id' => 'typing_1',
                'type' => 'typingIndicator',
                'data' => [
                    'label' => 'Typing',
                    'delay_seconds' => 3,
                ],
            ],
            [
                'id' => 'next_1',
                'type' => 'welcomeMessage',
                'data' => [
                    'label' => 'Hi',
                    'message' => 'After typing',
                ],
            ],
        ], [
            ['source' => 'typing_1', 'target' => 'next_1'],
        ]);

        $state = $this->activeState($campaign, 'typing_1');
        app(DripFlowEngine::class)->executeFromState($state);

        $state->refresh();
        $this->assertSame(ChatbotFlowStateStatus::Waiting, $state->status);
        $this->assertSame('next_1', $state->current_node_id);
        Queue::assertPushed(ExecuteDripStepJob::class);
    }

    public function test_function_call_builtin_stores_result(): void
    {
        $campaign = $this->campaignWithNodes([
            [
                'id' => 'fn_1',
                'type' => 'functionCall',
                'data' => [
                    'label' => 'Now',
                    'function_name' => 'contact_phone',
                    'result_variable' => 'phone_out',
                ],
            ],
        ]);

        $state = $this->activeState($campaign, 'fn_1');
        app(DripFlowEngine::class)->executeFromState($state);

        $state->refresh();
        $this->assertTrue((bool) ($state->variables['phone_out_success'] ?? false));
        $this->assertNotEmpty($state->variables['phone_out'] ?? null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function campaignWithNodes(array $nodes, array $edges = []): DripCampaign
    {
        return DripCampaign::factory()->create([
            'status' => ChatbotFlowStatus::Active,
            'exported_data' => [
                'nodes' => $nodes,
                'edges' => $edges,
            ],
        ]);
    }

    private function activeState(DripCampaign $campaign, string $nodeId): DripCampaignState
    {
        $contact = Contact::factory()->create();
        $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

        return DripCampaignState::create([
            'drip_campaign_id' => $campaign->id,
            'conversation_id' => $conversation->id,
            'current_node_id' => $nodeId,
            'status' => ChatbotFlowStateStatus::Active,
            'variables' => [],
        ]);
    }
}
