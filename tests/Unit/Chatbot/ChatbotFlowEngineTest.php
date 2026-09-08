<?php

namespace Tests\Unit\Chatbot;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Domains\Chatbot\Services\ChatbotNodeProcessor;
use App\Domains\Chatbot\Services\FlowDataNormalizer;
use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Domains\TriggerTemplate\Enums\TriggerFireResult;
use App\Enums\MessageDirection;
use App\Enums\MessageType;
use App\Models\ChatbotFlow;
use App\Models\Conversation;
use App\Models\Message;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ChatbotFlowEngineTest extends TestCase
{
    private ChatbotFlowEngine $engine;

    private ChatbotNodeProcessor&MockObject $nodeProcessor;

    private FlowDataNormalizer&MockObject $normalizer;

    private WalletService&MockObject $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nodeProcessor = $this->createMock(ChatbotNodeProcessor::class);
        $this->normalizer = $this->createMock(FlowDataNormalizer::class);
        $this->walletService = $this->createMock(WalletService::class);

        $this->walletService->method('balance')->willReturn(1000.0);

        $this->engine = new ChatbotFlowEngine(
            $this->nodeProcessor,
            $this->normalizer,
            new FlowVariableResolver(),
            $this->walletService,
        );
    }

    public function test_ignores_outbound_messages(): void
    {
        $conversation = new Conversation();
        $message = new Message([
            'body' => 'Hello',
            'direction' => MessageDirection::Outbound,
            'message_type' => MessageType::Text,
        ]);

        $result = $this->engine->processInbound($conversation, $message);

        $this->assertSame(TriggerFireResult::NoMatch, $result);
    }

    public function test_ignores_empty_body(): void
    {
        $conversation = new Conversation();
        $message = new Message([
            'body' => '   ',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
        ]);

        $result = $this->engine->processInbound($conversation, $message);

        $this->assertSame(TriggerFireResult::NoMatch, $result);
    }

    public function test_blocks_when_wallet_below_minimum(): void
    {
        $walletService = $this->createMock(WalletService::class);
        $walletService->method('balance')->willReturn(10.0);

        $engine = new ChatbotFlowEngine(
            $this->createMock(ChatbotNodeProcessor::class),
            $this->createMock(FlowDataNormalizer::class),
            new FlowVariableResolver(),
            $walletService,
        );

        $conversation = new Conversation();
        $message = new Message([
            'body' => 'hello',
            'direction' => MessageDirection::Inbound,
            'message_type' => MessageType::Text,
        ]);

        $result = $engine->processInbound($conversation, $message);

        $this->assertSame(TriggerFireResult::WalletBlocked, $result);
    }

    public function test_find_triggered_node_matches_exact_keyword(): void
    {
        $method = new \ReflectionMethod($this->engine, 'findTriggeredNode');
        $method->setAccessible(true);

        $nodeMap = [
            'welcome_1' => [
                'id' => 'welcome_1',
                'class' => 'welcomeMessage',
                'data' => [
                    'messageType' => 'text',
                    'triggerKeyword' => 'hello, hi, hey',
                    'text' => 'hello',
                ],
                'outputs' => [],
            ],
            'welcome_2' => [
                'id' => 'welcome_2',
                'class' => 'welcomeMessage',
                'data' => [
                    'messageType' => 'text',
                    'triggerKeyword' => 'pricing',
                ],
                'outputs' => [],
            ],
        ];

        // Exact match (case-insensitive)
        $this->assertSame('welcome_1', $method->invoke($this->engine, $nodeMap, 'hello'));
        $this->assertSame('welcome_1', $method->invoke($this->engine, $nodeMap, 'hi'));
        $this->assertSame('welcome_1', $method->invoke($this->engine, $nodeMap, 'hey'));
        $this->assertSame('welcome_2', $method->invoke($this->engine, $nodeMap, 'pricing'));

        // No partial match — must be exact
        $this->assertNull($method->invoke($this->engine, $nodeMap, 'hello there'));
        $this->assertNull($method->invoke($this->engine, $nodeMap, 'say hi'));
        $this->assertNull($method->invoke($this->engine, $nodeMap, 'unknown'));
    }

    public function test_find_triggered_node_skips_non_text_message_types(): void
    {
        $method = new \ReflectionMethod($this->engine, 'findTriggeredNode');
        $method->setAccessible(true);

        $nodeMap = [
            'welcome_1' => [
                'id' => 'welcome_1',
                'class' => 'welcomeMessage',
                'data' => [
                    'messageType' => 'template',
                    'triggerKeyword' => 'hello',
                ],
                'outputs' => [],
            ],
        ];

        $this->assertNull($method->invoke($this->engine, $nodeMap, 'hello'));
    }

    public function test_find_triggered_node_skips_non_welcome_nodes(): void
    {
        $method = new \ReflectionMethod($this->engine, 'findTriggeredNode');
        $method->setAccessible(true);

        $nodeMap = [
            'condition_1' => [
                'id' => 'condition_1',
                'class' => 'condition',
                'data' => ['triggerKeyword' => 'hello'],
                'outputs' => [],
            ],
        ];

        $this->assertNull($method->invoke($this->engine, $nodeMap, 'hello'));
    }

    public function test_resolve_next_node_from_reply_matches_handle_name(): void
    {
        $method = new \ReflectionMethod($this->engine, 'resolveNextNodeFromReply');
        $method->setAccessible(true);

        $node = [
            'id' => 'interactive_1',
            'outputs' => [
                'yes' => ['connections' => [['node' => 'node_yes']]],
                'no' => ['connections' => [['node' => 'node_no']]],
            ],
        ];

        $this->assertSame('node_yes', $method->invoke($this->engine, $node, 'yes', []));
        $this->assertSame('node_no', $method->invoke($this->engine, $node, 'no', []));
    }

    public function test_resolve_next_node_from_reply_matches_stored_options(): void
    {
        $method = new \ReflectionMethod($this->engine, 'resolveNextNodeFromReply');
        $method->setAccessible(true);

        $node = [
            'id' => 'interactive_1',
            'outputs' => [
                'option_1' => ['connections' => [['node' => 'node_a']]],
                'option_2' => ['connections' => [['node' => 'node_b']]],
            ],
        ];

        $variables = [
            '_interactive_options' => [
                ['title' => 'Plan A', 'id' => 'option_1'],
                ['title' => 'Plan B', 'id' => 'option_2'],
            ],
        ];

        $this->assertSame('node_a', $method->invoke($this->engine, $node, 'Plan A', $variables));
        $this->assertSame('node_b', $method->invoke($this->engine, $node, 'plan b', $variables));
    }

    public function test_resolve_next_node_from_reply_matches_quick_replies(): void
    {
        $method = new \ReflectionMethod($this->engine, 'resolveNextNodeFromReply');
        $method->setAccessible(true);

        $node = [
            'id' => 'interactive_1',
            'outputs' => [
                'output_1' => ['connections' => [['node' => 'node_first']]],
                'output_2' => ['connections' => [['node' => 'node_second']]],
            ],
        ];

        $variables = [
            '_quick_replies' => ['Yes please', 'No thanks'],
        ];

        $this->assertSame('node_first', $method->invoke($this->engine, $node, 'Yes please', $variables));
        $this->assertSame('node_second', $method->invoke($this->engine, $node, 'no thanks', $variables));
    }

    public function test_resolve_next_node_returns_null_when_no_match(): void
    {
        $method = new \ReflectionMethod($this->engine, 'resolveNextNodeFromReply');
        $method->setAccessible(true);

        $node = [
            'id' => 'interactive_1',
            'outputs' => [
                'yes' => ['connections' => [['node' => 'node_yes']]],
            ],
        ];

        $this->assertNull($method->invoke($this->engine, $node, 'maybe', []));
    }
}
