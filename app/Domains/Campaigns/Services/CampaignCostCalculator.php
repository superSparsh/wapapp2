<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Models\Campaign;
use App\Models\Template;

class CampaignCostCalculator
{
    /**
     * Estimate template message cost for a campaign.
     *
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string}
     */
    public function estimate(Campaign $campaign): array
    {
        $recipients = max(0, (int) $campaign->total_recipients);
        $template = $campaign->template;

        $unitCost = $this->unitCostForTemplate($template);
        $currency = (string) config('campaigns.cost.currency', 'INR');

        return [
            'recipients' => $recipients,
            'unit_cost' => $unitCost,
            'total_cost' => round($unitCost * $recipients, 2),
            'currency' => $currency,
        ];
    }

    private function unitCostForTemplate(?Template $template): float
    {
        if ($template === null) {
            return 0.0;
        }

        $category = strtoupper((string) $template->category);
        $rates = (array) config('campaigns.cost.category_rates', []);

        return (float) ($rates[$category] ?? $rates['DEFAULT'] ?? 0.78);
    }
}
