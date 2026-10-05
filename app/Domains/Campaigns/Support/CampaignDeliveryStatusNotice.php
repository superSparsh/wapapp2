<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Support;

use App\Models\Campaign;
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

        if ($this->pastAutoHideWindow($campaign)) {
            return false;
        }

        if ($metrics === null) {
            return false;
        }

        $pending = (int) ($metrics['pending'] ?? 0);
        $sent = (int) ($metrics['sent'] ?? 0);

        // Still sending, or waiting on delivery/failed confirmations from WhatsApp.
        if ($pending > 0 || $sent > 0) {
            return true;
        }

        if ($campaign->isSending() || $campaign->isPaused()) {
            return true;
        }

        return false;
    }

    private function pastAutoHideWindow(Campaign $campaign): bool
    {
        $anchor = $campaign->started_at
            ?? $campaign->completed_at
            ?? $campaign->updated_at;

        if (! $anchor instanceof Carbon) {
            return false;
        }

        return $anchor->lt(now()->subHours(self::AUTO_HIDE_AFTER_HOURS));
    }
}
