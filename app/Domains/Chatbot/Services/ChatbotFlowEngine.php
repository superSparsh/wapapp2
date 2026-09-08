<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Domains\Chatbot\Support\FlowVariableResolver;
use App\Domains\TriggerTemplate\Enums\TriggerFireResult;
use App\Enums\ChatbotFlowStateStatus;
use App\Enums\ChatbotFlowStatus;
use App\Enums\MessageDirection;
use App\Models\ChatbotFlow;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatbotFlowEngine
{
    /**
     * Maximum iterations per flow execution to prevent infinite loops.
     */
    private const MAX_ITERATIONS = 50;

    /**
     * Track processed node IDs within a single execution to detect cycles.
     *
     * @var array<string, bool>
     */
    private array $processedNodeIds = [];

    public function __construct(
        private readonly ChatbotNodeProcessor $nodeProcessor,
        private readonly FlowDataNormalizer $normalizer,
        private readonly FlowVariableResolver $variableResolver,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Main entry point: process an inbound message through the chatbot engine.
     *
     * Called from InboundMessageHandler after recording the inbound message.
     */
    public function processInbound(Conversation $conversation, Message $inboundMessage): TriggerFireResult
    {
        if ($inboundMessage->direction !== MessageDirection::Inbound) {
            return TriggerFireResult::NoMatch;
        }

        $body = trim((string) $inboundMessage->body);

        if ($body === '') {
            return TriggerFireResult::NoMatch;
        }

        // Wallet balance check
        if ($this->walletService->balance() <= (float) config('chatbot.wallet_min_balance', 50)) {
            return TriggerFireResult::WalletBlocked;
        }

        // 1. Check for "start" command → reset any existing states
        if ($this->isStartCommand($body)) {
            $this->resetConversationStates($conversation);
        }

        // 2. Check for existing waiting state → process reply
        $waitingState = $this->findWaitingState($conversation);

        if ($waitingState !== null) {
            return $this->processReply($conversation, $inboundMessage, $waitingState, $body);
        }

        // 3. Check for existing active state → continue flow
        $activeState = $this->findActiveState($conversation);

        if ($activeState !== null) {
            return $this->continueFromState($conversation, $activeState);
        }

        // 4. No active state → scan all active flows for keyword triggers
        return $this->matchAndTriggerFlow($conversation, $inboundMessage, $body);
    }

    /**
     * Continue flow execution from a given state (used by delayed jobs too).
     */
    public function continueFromState(Conversation $conversation, ChatbotFlowState $state): TriggerFireResult
    {
        $flow = ChatbotFlow::query()->find($state->chatbot_flow_id);

        if ($flow === null || ! $flow->isActive() || ! $flow->hasFlowData()) {
            $this->completeState($state);

            return TriggerFireResult::NoMatch;
        }

        $nodeMap = $this->normalizer->normalize($flow);

        if ($nodeMap === []) {
            $this->completeState($state);

            return TriggerFireResult::NoMatch;
        }

        $this->processedNodeIds = [];

        return $this->executeFlowFromNode($flow, $nodeMap, $conversation, $state);
    }

    // ─── Private: Start Command ─────────────────────────────────────

    private function isStartCommand(string $body): bool
    {
        $command = (string) config('chatbot.start_command', 'start');

        return mb_strtolower(trim($body)) === mb_strtolower($command);
    }

    private function resetConversationStates(Conversation $conversation): void
    {
        ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->whereIn('status', [ChatbotFlowStateStatus::Active->value, ChatbotFlowStateStatus::Waiting->value])
            ->update([
                'status' => ChatbotFlowStateStatus::Expired,
                'processed_at' => now(),
            ]);
    }

    // ─── Private: State Lookups ──────────────────────────────────────

    private function findWaitingState(Conversation $conversation): ?ChatbotFlowState
    {
        return ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->waiting()
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    private function findActiveState(Conversation $conversation): ?ChatbotFlowState
    {
        return ChatbotFlowState::query()
            ->forConversation($conversation->id)
            ->active()
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    // ─── Private: Reply Processing ──────────────────────────────────

    private function processReply(
        Conversation $conversation,
        Message $inboundMessage,
        ChatbotFlowState $state,
        string $replyBody,
    ): TriggerFireResult {
        $flow = ChatbotFlow::query()->find($state->chatbot_flow_id);

        if ($flow === null || ! $flow->isActive()) {
            $state->markExpired();

            return TriggerFireResult::NoMatch;
        }

        $nodeMap = $this->normalizer->normalize($flow);
        $currentNodeId = $state->current_node_id;
        $currentNode = $nodeMap[$currentNodeId] ?? null;

        if ($currentNode === null) {
            $state->markExpired();

            return TriggerFireResult::NoMatch;
        }

        // Store the user's response in variables
        $waitVariableName = (string) ($state->variables['_wait_variable_name'] ?? 'user_response');
        $variables = $state->variables ?? [];
        $variables[$waitVariableName] = $replyBody;
        $variables['_last_reply'] = $replyBody;
        $variables['_last_reply_type'] = (string) $inboundMessage->message_type->value;

        $state->forceFill([
            'variables' => $variables,
            'status' => ChatbotFlowStateStatus::Active,
        ])->save();

        // Find the next node based on the reply
        $nextNodeId = $this->resolveNextNodeFromReply($currentNode, $replyBody, $variables);

        if ($nextNodeId === null) {
            // No matching branch → use default output
            $nextNodeId = $this->defaultNextNodeId($currentNode);
        }

        if ($nextNodeId === null || ! isset($nodeMap[$nextNodeId])) {
            $this->completeState($state);

            return TriggerFireResult::Fired;
        }

        $state->forceFill(['current_node_id' => $nextNodeId])->save();

        $this->processedNodeIds = [];
        $result = $this->executeFlowFromNode($flow, $nodeMap, $conversation, $state);

        $this->markConversationRead($conversation);

        return $result;
    }

    /**
     * Resolve next node from a user reply to an interactive / quick-reply / carousel node.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $variables
     */
    private function resolveNextNodeFromReply(array $node, string $replyBody, array $variables): ?string
    {
        $outputs = $node['outputs'] ?? [];
        $replyLower = mb_strtolower(trim($replyBody));

        // Try to match reply against output handles (button IDs, option text, etc.)
        foreach ($outputs as $handle => $output) {
            $connections = $output['connections'] ?? [];

            if ($connections === []) {
                continue;
            }

            $handleLower = mb_strtolower($handle);

            // Match by handle name (e.g., "yes", "no", "option_1")
            if ($handleLower === $replyLower || str_contains($handleLower, $replyLower)) {
                return (string) $connections[0]['node'];
            }
        }

        // Try matching against stored interactive options
        $options = $variables['_interactive_options'] ?? [];

        if (is_array($options)) {
            foreach ($options as $option) {
                $title = mb_strtolower((string) ($option['title'] ?? ''));
                $id = mb_strtolower((string) ($option['id'] ?? ''));

                if ($title === $replyLower || $id === $replyLower) {
                    // Find the output handle matching this option
                    $handleKey = $id !== '' ? $id : $title;

                    foreach ($outputs as $handle => $output) {
                        $connections = $output['connections'] ?? [];

                        if ($connections === []) {
                            continue;
                        }

                        if (str_contains(mb_strtolower($handle), $handleKey)) {
                            return (string) $connections[0]['node'];
                        }
                    }
                }
            }
        }

        // Try matching against quick replies
        $quickReplies = $variables['_quick_replies'] ?? [];

        if (is_array($quickReplies)) {
            foreach ($quickReplies as $index => $reply) {
                $replyTitle = is_string($reply) ? $reply : (string) ($reply['title'] ?? $reply['text'] ?? '');

                if (mb_strtolower($replyTitle) === $replyLower) {
                    $handleKey = 'output_'.($index + 1);
                    $nextId = $this->nextNodeIdFromHandle($node, $handleKey);

                    if ($nextId !== null) {
                        return $nextId;
                    }
                }
            }
        }

        return null;
    }

    // ─── Private: Keyword Matching & Flow Triggering ─────────────────

    private function matchAndTriggerFlow(
        Conversation $conversation,
        Message $inboundMessage,
        string $body,
    ): TriggerFireResult {
        $lineId = (int) $conversation->whatsapp_line_id;
        $messageLower = mb_strtolower($body);

        $flows = ChatbotFlow::query()
            ->active()
            ->where(function ($query) use ($lineId): void {
                $query->whereNull('whatsapp_line_id')
                    ->orWhere('whatsapp_line_id', $lineId);
            })
            ->orderByDesc('id')
            ->get();

        foreach ($flows as $flow) {
            if (! $flow->hasFlowData()) {
                continue;
            }

            $nodeMap = $this->normalizer->normalize($flow);

            if ($nodeMap === []) {
                continue;
            }

            // Scan for welcome/template nodes with trigger keywords
            $triggeredNodeId = $this->findTriggeredNode($nodeMap, $messageLower);

            if ($triggeredNodeId === null) {
                continue;
            }

            return $this->startFlow($flow, $nodeMap, $conversation, $triggeredNodeId, $body);
        }

        return TriggerFireResult::NoMatch;
    }

    /**
     * Find a node in the flow that has a trigger keyword matching the user message.
     * Only welcome and template message nodes with text messageType are scanned.
     *
     * @param  array<string, array<string, mixed>>  $nodeMap
     */
    private function findTriggeredNode(array $nodeMap, string $messageLower): ?string
    {
        foreach ($nodeMap as $nodeId => $node) {
            $nodeType = (string) ($node['class'] ?? 'unknown');

            if (! in_array($nodeType, ['welcomeMessage', 'templateMessage'], true)) {
                continue;
            }

            $data = $node['data'] ?? [];
            $messageType = (string) ($data['messageType'] ?? 'text');

            if ($messageType !== 'text') {
                continue;
            }

            $triggerKeyword = (string) ($data['triggerKeyword'] ?? $data['text'] ?? '');

            if ($triggerKeyword === '') {
                continue;
            }

            // Split comma-separated keywords and check exact match only
            $keywords = array_filter(
                array_map(fn (string $kw): string => mb_strtolower(trim($kw)), explode(',', $triggerKeyword)),
            );

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && $messageLower === $keyword) {
                    return $nodeId;
                }
            }
        }

        return null;
    }

    /**
     * Start a new flow execution from a triggered node.
     *
     * @param  array<string, array<string, mixed>>  $nodeMap
     */
    private function startFlow(
        ChatbotFlow $flow,
        array $nodeMap,
        Conversation $conversation,
        string $startNodeId,
        string $body = '',
    ): TriggerFireResult {
        try {
            // Create conversation state
            $state = ChatbotFlowState::query()->create([
                'conversation_id' => $conversation->id,
                'chatbot_flow_id' => $flow->id,
                'current_node_id' => $startNodeId,
                'variables' => [
                    '_flow_name' => $flow->name,
                    '_start_node' => $startNodeId,
                    '_last_reply' => $body,
                    'user_response' => $body,
                ],
                'status' => ChatbotFlowStateStatus::Active,
                'expires_at' => now()->addMinutes((int) config('chatbot.state_ttl_minutes', 2)),
            ]);

            $this->processedNodeIds = [];

            $result = $this->executeFlowFromNode($flow, $nodeMap, $conversation, $state);

            if ($result === TriggerFireResult::Fired) {
                $this->markConversationRead($conversation);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('Chatbot flow start error', [
                'flow_id' => $flow->id,
                'start_node' => $startNodeId,
                'error' => $e->getMessage(),
            ]);

            return TriggerFireResult::SendFailed;
        }
    }

    // ─── Private: Flow Execution ─────────────────────────────────────

    /**
     * Execute flow nodes sequentially starting from the state's current node.
     * Processes nodes in a loop until execution halts (wait, delay, complete, error).
     *
     * @param  array<string, array<string, mixed>>  $nodeMap
     */
    private function executeFlowFromNode(
        ChatbotFlow $flow,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): TriggerFireResult {
        $iterations = 0;
        $anyMessageSent = false;
        $nodeVisitCounts = [];

        while ($iterations < self::MAX_ITERATIONS) {
            $iterations++;
            $currentNodeId = $state->current_node_id;

            // Loop safety: Allow controlled loop-backs (up to 5 visits per node per run)
            $nodeVisitCounts[$currentNodeId] = ($nodeVisitCounts[$currentNodeId] ?? 0) + 1;

            if ($nodeVisitCounts[$currentNodeId] > 5) {
                $this->logDebug("Loop limit exceeded at node: {$currentNodeId}");

                break;
            }

            $node = $nodeMap[$currentNodeId] ?? null;

            if ($node === null) {
                $this->logDebug("Node not found: {$currentNodeId}");

                break;
            }

            // Process the node
            $result = $this->nodeProcessor->process($node, $nodeMap, $conversation, $state);

            $this->logDebug("Node {$currentNodeId} (".($node['class'] ?? '?').") → {$result->value}");

            // Track if any message was sent
            if (! $result->haltsExecution() || $result === NodeProcessResult::WaitForResponse) {
                $anyMessageSent = true;
            }

            // Handle result
            if ($result->haltsExecution()) {
                if ($result === NodeProcessResult::WaitForResponse) {
                    // State is already updated by WaitForResponse processor
                    $this->refreshExpiry($state);

                    return TriggerFireResult::Fired;
                }

                if ($result === NodeProcessResult::Delayed) {
                    return TriggerFireResult::Fired;
                }

                // Completed or Error
                $this->completeState($state);

                return $anyMessageSent ? TriggerFireResult::Fired : TriggerFireResult::NoMatch;
            }

            // Continue → refresh the state to get updated current_node_id
            $state->refresh();

            if ($state->status->isTerminal()) {
                break;
            }

            // If the processor advanced the state to a different node, remove
            // that node from processedNodeIds so it can be processed (prevents
            // false cycle detection when sequential processors advance state).
            if ($state->current_node_id !== $currentNodeId) {
                unset($this->processedNodeIds[$state->current_node_id]);
            } elseif ($state->current_node_id === $currentNodeId) {
                // Node didn't advance and result was Continue → stuck
                $this->logDebug("State stuck at node: {$currentNodeId}");

                break;
            }

            $this->refreshExpiry($state);
        }

        // If we exhausted iterations or hit a cycle, complete the state
        $this->completeState($state);

        return $anyMessageSent ? TriggerFireResult::Fired : TriggerFireResult::NoMatch;
    }

    // ─── Private: Helpers ────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $node
     */
    private function defaultNextNodeId(array $node): ?string
    {
        $outputs = $node['outputs'] ?? [];

        // Try output_1, then default
        foreach (['output_1', 'default'] as $handle) {
            $nextId = $this->nextNodeIdFromHandle($node, $handle);

            if ($nextId !== null) {
                return $nextId;
            }
        }

        // Fall back to first available
        foreach ($outputs as $output) {
            $connections = $output['connections'] ?? [];

            if (isset($connections[0]['node'])) {
                return (string) $connections[0]['node'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function nextNodeIdFromHandle(array $node, string $handle): ?string
    {
        $connections = $node['outputs'][$handle]['connections'] ?? [];

        return isset($connections[0]['node']) ? (string) $connections[0]['node'] : null;
    }

    private function completeState(ChatbotFlowState $state): void
    {
        $state->markCompleted();
    }

    private function refreshExpiry(ChatbotFlowState $state): void
    {
        $ttl = (int) config('chatbot.state_ttl_minutes', 2);
        $state->forceFill(['expires_at' => now()->addMinutes($ttl)])->save();
    }

    private function markConversationRead(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            $conversation->forceFill(['unread_count' => 0])->save();
        });
    }

    private function logDebug(string $message): void
    {
        if (config('chatbot.debug')) {
            Log::debug("[ChatbotEngine] {$message}");
        }
    }
}
