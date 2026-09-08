<?php

namespace Tests\Unit\Chatbot;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Services\NodeTypes\ConditionProcessor;
use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\ChatbotFlowStateStatus;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ConditionProcessorTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private ConditionProcessor $processor;

    private InboxOutboundService&MockObject $outboundService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->outboundService = $this->createMock(InboxOutboundService::class);
        $this->processor = new ConditionProcessor(
            $this->outboundService,
            new FlowVariableResolver(),
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_equals_operator_matches_and_advances_to_yes_branch(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: 'yes',
            operator: 'equals',
            compareValue: 'yes',
        );

        $result = $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame(NodeProcessResult::Continue, $result);
        $this->assertSame('node_yes', $state->current_node_id);
    }

    public function test_equals_operator_no_match_advances_to_no_branch(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: 'no',
            operator: 'equals',
            compareValue: 'yes',
        );

        $result = $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame(NodeProcessResult::Continue, $result);
        $this->assertSame('node_no', $state->current_node_id);
    }

    public function test_contains_operator(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: 'I love pizza',
            operator: 'contains',
            compareValue: 'pizza',
        );

        $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame('node_yes', $state->current_node_id);
    }

    public function test_greater_than_operator(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: '100',
            operator: 'greater_than',
            compareValue: '50',
        );

        $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame('node_yes', $state->current_node_id);
    }

    public function test_is_empty_operator(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: '',
            operator: 'is_empty',
            compareValue: '',
        );

        $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame('node_yes', $state->current_node_id);
    }

    public function test_is_not_empty_operator(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: 'hello',
            operator: 'is_not_empty',
            compareValue: '',
        );

        $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame('node_yes', $state->current_node_id);
    }

    public function test_case_insensitive_equals(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: 'HELLO',
            operator: 'equals',
            compareValue: 'hello',
        );

        $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame('node_yes', $state->current_node_id);
    }

    public function test_starts_with_operator(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: 'hello world',
            operator: 'starts_with',
            compareValue: 'hello',
        );

        $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame('node_yes', $state->current_node_id);
    }

    public function test_regex_operator(): void
    {
        [$node, $nodeMap, $state, $conversation] = $this->buildScenario(
            fieldValue: 'order-12345',
            operator: 'regex',
            compareValue: 'order-\d+',
        );

        $this->processor->process($node, $nodeMap, $conversation, $state);

        $this->assertSame('node_yes', $state->current_node_id);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: ChatbotFlowState, 3: Conversation}
     */
    private function buildScenario(string $fieldValue, string $operator, string $compareValue): array
    {
        $conversation = Conversation::factory()->create();
        $flow = \App\Models\ChatbotFlow::factory()->active()->create();

        $node = [
            'id' => 'cond_1',
            'class' => 'condition',
            'data' => [
                'field' => 'answer',
                'operator' => $operator,
                'value' => $compareValue,
            ],
            'outputs' => [
                'output_yes' => ['connections' => [['node' => 'node_yes']]],
                'output_no' => ['connections' => [['node' => 'node_no']]],
            ],
        ];

        $nodeMap = [
            'cond_1' => $node,
            'node_yes' => ['id' => 'node_yes', 'class' => 'welcomeMessage', 'data' => [], 'outputs' => []],
            'node_no' => ['id' => 'node_no', 'class' => 'welcomeMessage', 'data' => [], 'outputs' => []],
        ];

        $state = new ChatbotFlowState([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'cond_1',
            'variables' => ['answer' => $fieldValue],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addMinutes(2),
        ]);

        return [$node, $nodeMap, $state, $conversation];
    }

    public function test_no_matching_branch_completes_state(): void
    {
        $conversation = Conversation::factory()->create();
        $flow = \App\Models\ChatbotFlow::factory()->active()->create();

        // Node with no output connections
        $node = [
            'id' => 'cond_1',
            'class' => 'condition',
            'data' => [
                'field' => 'answer',
                'operator' => 'equals',
                'value' => 'yes',
            ],
            'outputs' => [
                'output_yes' => ['connections' => []],
                'output_no' => ['connections' => []],
            ],
        ];

        $state = new ChatbotFlowState([
            'conversation_id' => $conversation->id,
            'chatbot_flow_id' => $flow->id,
            'current_node_id' => 'cond_1',
            'variables' => ['answer' => 'yes'],
            'status' => ChatbotFlowStateStatus::Active,
            'expires_at' => now()->addMinutes(2),
        ]);

        $result = $this->processor->process($node, [], $conversation, $state);

        $this->assertSame(NodeProcessResult::Completed, $result);
    }
}
