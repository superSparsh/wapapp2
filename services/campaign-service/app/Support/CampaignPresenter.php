<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CampaignStatus;
use App\Models\Campaign;

class CampaignPresenter
{
    /**
     * Format campaign data for index list card.
     *
     * @return array{name: string, audience: string, status_label: string, status_variant: string, recipients: int, completion_rate: string, created_at: string, scheduled_at: string|null}
     */
    public function indexCard(Campaign $campaign): array
    {
        $statusVariant = match ($campaign->status) {
            CampaignStatus::Draft => 'new',
            CampaignStatus::Scheduled => 'fd-draft',
            CampaignStatus::Sending => 'sending',
            CampaignStatus::Completed => 'fd-approved',
            CampaignStatus::Paused => 'paused',
            CampaignStatus::Cancelled => 'cancelled',
            default => 'default',
        };

        return [
            'name' => $campaign->name,
            'audience_id' => $campaign->audience_id,
            'whatsapp_line_id' => $campaign->whatsapp_line_id,
            'template_id' => $campaign->template_id,
            'status_label' => $campaign->status?->label() ?? 'Unknown',
            'status_variant' => $statusVariant,
            'recipients' => $campaign->total_recipients,
            'completion_rate' => $campaign->completionRate(),
            'created_at' => $campaign->created_at?->format('d M Y h:i A') ?? '',
            'scheduled_at' => $campaign->scheduled_at?->format('d M Y h:i A'),
        ];
    }
}
