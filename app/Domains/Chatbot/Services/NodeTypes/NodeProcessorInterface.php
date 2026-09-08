<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services\NodeTypes;

use App\Domains\Chatbot\Enums\NodeProcessResult;
use App\Models\ChatbotFlowState;
use App\Models\Conversation;

interface NodeProcessorInterface
{
    /**
     * Process a single node in a chatbot flow.
     *
     * @param  array<string, mixed>  $node       Normalized node data {id, class, data, outputs}
     * @param  array<string, array<string, mixed>>  $nodeMap  Full node map for the flow
     * @return NodeProcessResult
     */
    public function process(
        array $node,
        array $nodeMap,
        Conversation $conversation,
        ChatbotFlowState $state,
    ): NodeProcessResult;
}
