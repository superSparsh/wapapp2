<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Support;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Carbon;

/**
 * In-app notice while WhatsApp delivery/read webhooks are still catching up.
 */
final class CampaignDeliveryStatusNotice
{
    public const MESSAGE = 'Your campaign was sent successfully. We are still receiving delivery and read updates from WhatsApp. '
        .'For larger lists this can take some time. Reports and wallet activity update as each message is confirmed.';

    /** Hide the banner this many hours after the send window started (even if a few statuses lag). */
    public const AUTO_HIDE_AFTER_HOURS = 24;

    /**
     * @param  array{pending?: int, sent?: int, total?: int, delivered?: int, failed?: int}|null  $metrics
     */
    public function shouldShow(Campaign $campaign, ?array $metrics = null): bool
    {
        if ($campaign->isDraft() || $campaign->isScheduled()) {
            return false;
        }

        if ($metrics === null) {
            return false;
        }

        // Stats fall back to campaign aggregate columns when there are no recipient rows;
        // those numbers are often stale and would show this banner on every old campaign.
        if (! CampaignRecipient::query()->where('campaign_id', $campaign->id)->exists()) {
            return false;
        }

        if ($this->pastAutoHideWindow($campaign)) {
            return false;
        }

        $awaiting = (int) ($metrics['pending'] ?? 0) + (int) ($metrics['sent'] ?? 0);

        return $awaiting > 0;
    }

    private function pastAutoHideWindow(Campaign $campaign): bool
    {
        $anchor = $campaign->started_at ?? $campaign->completed_at;

        // No send timestamp → treat as settled (do not show on legacy rows).
        if (! $anchor instanceof Carbon) {
            return true;
        }

        return $anchor->lt(now()->subHours(self::AUTO_HIDE_AFTER_HOURS));
    }
}
