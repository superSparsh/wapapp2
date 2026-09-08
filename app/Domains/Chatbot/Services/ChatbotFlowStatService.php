<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Services;

use App\Enums\ChatbotFlowStatAction;
use App\Models\ChatbotFlowStat;
use App\Models\Conversation;

class ChatbotFlowStatService
{
    /**
     * Record a stat entry for a flow node execution.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        int $flowId,
        string $nodeId,
        string $nodeType,
        ChatbotFlowStatAction $action,
        ?Conversation $conversation = null,
        ?string $contactPhone = null,
        ?array $metadata = null,
    ): ChatbotFlowStat {
        return ChatbotFlowStat::query()->create([
            'chatbot_flow_id' => $flowId,
            'conversation_id' => $conversation?->id,
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'contact_phone' => $contactPhone ?? $conversation?->contact_phone,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get aggregate stats for a flow.
     *
     * @return array{
     *     total_entered: int,
     *     total_completed: int,
     *     total_dropped: int,
     *     total_errors: int,
     *     completion_rate: float,
     *     drop_rate: float,
     *     unique_contacts: int,
     *     top_nodes: array,
     * }
     */
    public function aggregate(int $flowId): array
    {
        $stats = ChatbotFlowStat::query()
            ->where('chatbot_flow_id', $flowId)
            ->selectRaw("
                COUNT(CASE WHEN action = ? THEN 1 END) as total_entered,
                COUNT(CASE WHEN action = ? THEN 1 END) as total_completed,
                COUNT(CASE WHEN action = ? THEN 1 END) as total_dropped,
                COUNT(CASE WHEN action = ? THEN 1 END) as total_errors,
                COUNT(DISTINCT contact_phone) as unique_contacts
            ", [
                ChatbotFlowStatAction::Entered->value,
                ChatbotFlowStatAction::Completed->value,
                ChatbotFlowStatAction::Dropped->value,
                ChatbotFlowStatAction::Error->value,
            ])
            ->first();

        $entered = (int) ($stats->total_entered ?? 0);
        $completed = (int) ($stats->total_completed ?? 0);
        $dropped = (int) ($stats->total_dropped ?? 0);
        $errors = (int) ($stats->total_errors ?? 0);

        return [
            'total_entered' => $entered,
            'total_completed' => $completed,
            'total_dropped' => $dropped,
            'total_errors' => $errors,
            'completion_rate' => $entered > 0 ? round(($completed / $entered) * 100, 2) : 0.0,
            'drop_rate' => $entered > 0 ? round(($dropped / $entered) * 100, 2) : 0.0,
            'unique_contacts' => (int) ($stats->unique_contacts ?? 0),
            'top_nodes' => $this->topNodes($flowId),
        ];
    }

    /**
     * Get the top nodes by activity count.
     *
     * @return array<int, array{node_id: string, node_type: string, count: int}>
     */
    public function topNodes(int $flowId, int $limit = 10): array
    {
        return ChatbotFlowStat::query()
            ->where('chatbot_flow_id', $flowId)
            ->where('action', ChatbotFlowStatAction::Entered)
            ->selectRaw('node_id, node_type, COUNT(*) as count')
            ->groupBy('node_id', 'node_type')
            ->orderByDesc('count')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'node_id' => $row->node_id,
                'node_type' => $row->node_type,
                'count' => (int) $row->count,
            ])
            ->all();
    }
}
