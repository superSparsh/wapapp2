<?php

declare(strict_types=1);

namespace App\Domains\Drip\Services;

use App\Enums\ChatbotFlowStatAction;
use App\Models\Conversation;
use App\Models\DripCampaignStat;

class DripCampaignStatService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        int $campaignId,
        string $nodeId,
        string $nodeType,
        ChatbotFlowStatAction $action,
        ?Conversation $conversation = null,
        ?string $contactPhone = null,
        ?array $metadata = null,
    ): DripCampaignStat {
        return DripCampaignStat::query()->create([
            'drip_campaign_id' => $campaignId,
            'conversation_id' => $conversation?->id,
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'contact_phone' => $contactPhone ?? $conversation?->contact_phone,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }

    /**
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
    public function aggregate(int $campaignId): array
    {
        $stats = DripCampaignStat::query()
            ->where('drip_campaign_id', $campaignId)
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

        return [
            'total_entered' => $entered,
            'total_completed' => $completed,
            'total_dropped' => $dropped,
            'total_errors' => (int) ($stats->total_errors ?? 0),
            'completion_rate' => $entered > 0 ? round(($completed / $entered) * 100, 2) : 0.0,
            'drop_rate' => $entered > 0 ? round(($dropped / $entered) * 100, 2) : 0.0,
            'unique_contacts' => (int) ($stats->unique_contacts ?? 0),
            'top_nodes' => [],
        ];
    }
}
