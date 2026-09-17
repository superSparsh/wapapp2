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
        $replyId = (string) (($inboundMessage->metadata['interactive_reply_id'] ?? null)
            ?: ($inboundMessage->metadata['reply_id'] ?? ''));
        if ($replyId !== '') {
            $variables['_last_reply_id'] = $replyId;
        }

        $state->forceFill([
            'variables' => $variables,
            'status' => ChatbotFlowStateStatus::Active,
        ])->save();

        // Find the next node based on the reply
        $nextNodeId = $this->resolveNextNodeFromReply($currentNode, $replyBody, $variables, $replyId);

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
     * Matches legacy React handles: button-{i}, interactive-{section}-{row}, carousel-{card}-{btn}.
     *
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $variables
     */
    private function resolveNextNodeFromReply(
        array $node,
        string $replyBody,
        array $variables,
        string $replyId = '',
    ): ?string {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $nodeClass = (string) ($node['class'] ?? $node['type'] ?? '');
        $interactiveType = strtolower((string) ($data['interactiveType'] ?? $data['interactive_type'] ?? $data['type'] ?? ''));

        if ($nodeClass === 'interactiveMessage' || in_array($interactiveType, ['button', 'list'], true)) {
            $fromInteractive = $this->resolveInteractiveHandle($node, $data, $replyBody, $replyId);
            if ($fromInteractive !== null) {
                return $fromInteractive;
            }
        }

        if ($nodeClass === 'carouselTemplate') {
            $fromCarousel = $this->resolveCarouselHandle($node, $data, $replyBody);
            if ($fromCarousel !== null) {
                return $fromCarousel;
            }
        }

        $outputs = $node['outputs'] ?? [];
        $replyLower = mb_strtolower(trim($replyBody));
        $replyIdLower = mb_strtolower(trim($replyId));

        // Direct handle match (exact)
        foreach ($outputs as $handle => $output) {
            $connections = $output['connections'] ?? [];
            if ($connections === []) {
                continue;
            }
            $handleLower = mb_strtolower((string) $handle);
            if ($handleLower === $replyLower || ($replyIdLower !== '' && $handleLower === $replyIdLower)) {
                return (string) $connections[0]['node'];
            }
        }

        // Stored interactive options → button-{index} / id / title
        $options = $variables['_interactive_options'] ?? [];
        if (is_array($options)) {
            foreach ($options as $index => $option) {
                if (! is_array($option)) {
                    continue;
                }
                $title = mb_strtolower(trim((string) ($option['title'] ?? '')));
                $id = mb_strtolower(trim((string) ($option['id'] ?? '')));

                if (($title !== '' && $this->labelsMatch($title, $replyLower))
                    || ($id !== '' && ($id === $replyLower || $id === $replyIdLower))) {
                    foreach (['button-'.$index, $id, $title] as $handleKey) {
                        if ($handleKey === '') {
                            continue;
                        }
                        $nextId = $this->nextNodeIdFromHandle($node, (string) $handleKey);
                        if ($nextId !== null) {
                            return $nextId;
                        }
                    }
                }
            }
        }

        // Quick replies → output_{n} / button-{n}
        $quickReplies = $variables['_quick_replies'] ?? [];
        if (is_array($quickReplies)) {
            foreach ($quickReplies as $index => $reply) {
                $replyTitle = is_string($reply)
                    ? $reply
                    : (string) ($reply['title'] ?? $reply['text'] ?? '');

                if (! $this->labelsMatch($replyTitle, $replyBody)) {
                    continue;
                }

                foreach (['output_'.($index + 1), 'button-'.$index] as $handleKey) {
                    $nextId = $this->nextNodeIdFromHandle($node, $handleKey);
                    if ($nextId !== null) {
                        return $nextId;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $data
     */
    private function resolveInteractiveHandle(array $node, array $data, string $replyBody, string $replyId): ?string
    {
        $interactiveType = strtolower((string) ($data['interactiveType'] ?? $data['interactive_type'] ?? $data['type'] ?? 'button'));

        if ($interactiveType === 'button' || ($interactiveType === '' && isset($data['buttons']))) {
            foreach (array_values($data['buttons'] ?? []) as $buttonIndex => $button) {
                if (! is_array($button)) {
                    continue;
                }
                $buttonId = (string) ($button['id'] ?? '');
                $buttonTitle = (string) ($button['title'] ?? $button['text'] ?? $button['label'] ?? '');

                $matched = ($replyId !== '' && $buttonId !== '' && strcasecmp($replyId, $buttonId) === 0)
                    || ($buttonTitle !== '' && $this->labelsMatch($buttonTitle, $replyBody))
                    || ($buttonId !== '' && strcasecmp($buttonId, trim($replyBody)) === 0);

                if (! $matched) {
                    continue;
                }

                foreach (['button-'.$buttonIndex, $buttonId, $buttonTitle] as $handleKey) {
                    if ($handleKey === '') {
                        continue;
                    }
                    $nextId = $this->nextNodeIdFromHandle($node, (string) $handleKey);
                    if ($nextId !== null) {
                        return $nextId;
                    }
                }
            }
        }

        if ($interactiveType === 'list' || isset($data['sections']) || isset($data['list_sections'])) {
            $sections = $data['sections'] ?? $data['list_sections'] ?? [];
            foreach (array_values($sections) as $sectionIndex => $section) {
                if (! is_array($section)) {
                    continue;
                }
                foreach (array_values($section['rows'] ?? $section['items'] ?? []) as $rowIndex => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $rowId = (string) ($row['id'] ?? '');
                    $rowTitle = (string) ($row['title'] ?? $row['text'] ?? $row['label'] ?? '');

                    $matched = ($replyId !== '' && $rowId !== '' && strcasecmp($replyId, $rowId) === 0)
                        || ($rowTitle !== '' && $this->labelsMatch($rowTitle, $replyBody))
                        || ($rowId !== '' && strcasecmp($rowId, trim($replyBody)) === 0);

                    if (! $matched) {
                        continue;
                    }

                    foreach ([
                        'interactive-'.$sectionIndex.'-'.$rowIndex,
                        'interactive-0-'.$rowIndex,
                        $rowId,
                        $rowTitle,
                    ] as $handleKey) {
                        if ($handleKey === '') {
                            continue;
                        }
                        $nextId = $this->nextNodeIdFromHandle($node, (string) $handleKey);
                        if ($nextId !== null) {
                            return $nextId;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $data
     */
    private function resolveCarouselHandle(array $node, array $data, string $replyBody): ?string
    {
        foreach (array_values($data['templateCards'] ?? $data['cards'] ?? []) as $cardIndex => $card) {
            if (! is_array($card)) {
                continue;
            }
            foreach (array_values($card['buttons'] ?? []) as $buttonIndex => $button) {
                if (! is_array($button)) {
                    continue;
                }
                $buttonText = (string) ($button['text'] ?? $button['title'] ?? '');
                if ($buttonText === '' || ! $this->labelsMatch($buttonText, $replyBody)) {
                    continue;
                }
                $nextId = $this->nextNodeIdFromHandle($node, 'carousel-'.$cardIndex.'-'.$buttonIndex);
                if ($nextId !== null) {
                    return $nextId;
                }
            }
        }

        return null;
    }

    private function labelsMatch(string $a, string $b): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if (strcasecmp($a, $b) === 0) {
            return true;
        }

        return $this->normalizeLabel($a) === $this->normalizeLabel($b);
    }

    private function normalizeLabel(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Find a node in the flow that has a trigger keyword matching the user message.
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
            // Never use body/text as keyword — React stores the keyword separately.
            $triggerKeyword = (string) ($data['triggerKeyword'] ?? $data['keywords'] ?? '');

            if ($triggerKeyword === '') {
                continue;
            }

            $keywords = array_filter(
                array_map(fn (string $kw): string => mb_strtolower(trim($kw)), explode(',', $triggerKeyword)),
            );

            foreach ($keywords as $keyword) {
                if ($keyword === '') {
                    continue;
                }
                if ($this->messageMatchesKeyword($messageLower, $keyword)) {
                    return $nodeId;
                }
            }
        }

        return null;
    }

    private function messageMatchesKeyword(string $messageLower, string $keywordLower): bool
    {
        $messageLower = trim($messageLower);
        $keywordLower = trim($keywordLower);

        if ($messageLower === '' || $keywordLower === '') {
            return false;
        }

        if ($messageLower === $keywordLower) {
            return true;
        }

        // Whole-word / punctuated match (legacy parity): "hi!" / "hi there" for keyword "hi"
        $pattern = '/(?:^|[^\p{L}\p{N}])'.preg_quote($keywordLower, '/').'(?:[^\p{L}\p{N}]|$)/ui';

        return (bool) preg_match($pattern, $messageLower);
    }

    /**
     * Scan all active flows for a keyword match and start the matching flow.
     */
    private function matchAndTriggerFlow(
        Conversation $conversation,
        Message $inboundMessage,
        string $body,
    ): TriggerFireResult {
        $messageLower = mb_strtolower(trim($body));

        $flows = ChatbotFlow::query()
            ->where('status', ChatbotFlowStatus::Active)
            ->get();

        foreach ($flows as $flow) {
            if (! $flow->hasFlowData()) {
                continue;
            }

            $nodeMap = $this->normalizer->normalize($flow);
            $triggeredNodeId = $this->findTriggeredNode($nodeMap, $messageLower);

            if ($triggeredNodeId === null) {
                continue;
            }

            return $this->startFlow($flow, $nodeMap, $conversation, $triggeredNodeId, $body);
        }

        return TriggerFireResult::NoMatch;
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
