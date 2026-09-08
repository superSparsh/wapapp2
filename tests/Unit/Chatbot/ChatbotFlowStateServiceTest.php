<?php

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Services\ChatbotFlowStateService;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatus;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ChatbotFlowStateServiceTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private ChatbotFlowStateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->service = new ChatbotFlowStateService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_creates_active_state_with_ttl(): void
    {
        [$conversation, $flow] = $this->seedConversationAndFlow();

        $state = $this->service->create($conversation, $flow->id, 'node_1', ['foo' => 'bar']);

        $this->assertInstanceOf(ChatbotFlowState::class, $state);
        $this->assertSame(ChatbotFlowStateStatus::Active, $state->status);
        $this->assertSame('node_1', $state->current_node_id);
        $this->assertSame('bar', $state->variables['foo']);
        $this->assertTrue($state->expires_at->isFuture());
    }

    public function test_find_active_returns_active_state(): void
    {
        [$conversation, $flow] = $this->seedConversationAndFlow();

        $state = $this->service->create($conversation, $flow->id, 'node_1');

        $found = $this->service->findActive($conversation);

        $this->assertNotNull($found);
        $this->assertSame($state->id, $found->id);
    }

    public function test_find_active_returns_null_when_no_active_state(): void
    {
        [$conversation, $flow] = $this->seedConversationAndFlow();

        $state = $this->service->create($conversation, $flow->id, 'node_1');
        $state->forceFill(['status' => ChatbotFlowStateStatus::Completed])->save();

        $this->assertNull($this->service->findActive($conversation));
    }

    public function test_find_active_ignores_expired_states(): void
    {
        [$conversation, $flow] = $this->seedConversationAndFlow();

        $state = $this->service->create($conversation, $flow->id, 'node_1');
        $state->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertNull($this->service->findActive($conversation));
    }

    public function test_expire_all_marks_all_active_states_as_expired(): void
    {
        [$conversation, $flow] = $this->seedConversationAndFlow();

        $s1 = $this->service->create($conversation, $flow->id, 'node_1');
        $s2 = $this->service->create($conversation, $flow->id, 'node_2');
        $s2->forceFill(['status' => ChatbotFlowStateStatus::Waiting])->save();

        $affected = $this->service->expireAll($conversation);

        $this->assertSame(2, $affected);
        $this->assertSame(ChatbotFlowStateStatus::Expired, $s1->fresh()->status);
        $this->assertSame(ChatbotFlowStateStatus::Expired, $s2->fresh()->status);
    }

    public function test_expire_stale_states_only_affects_past_expiry(): void
    {
        [$conversation, $flow] = $this->seedConversationAndFlow();

        $stale = $this->service->create($conversation, $flow->id, 'node_1');
        $stale->forceFill(['expires_at' => now()->subMinute()])->save();

        $fresh = $this->service->create($conversation, $flow->id, 'node_2');

        $affected = $this->service->expireStaleStates();

        $this->assertSame(1, $affected);
        $this->assertSame(ChatbotFlowStateStatus::Expired, $stale->fresh()->status);
        $this->assertSame(ChatbotFlowStateStatus::Active, $fresh->fresh()->status);
    }

    /**
     * @return array{0: Conversation, 1: ChatbotFlow}
     */
    private function seedConversationAndFlow(): array
    {
        $conversation = Conversation::factory()->create();
        $flow = ChatbotFlow::factory()->create(['status' => ChatbotFlowStatus::Active]);

        return [$conversation, $flow];
    }
}
