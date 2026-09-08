<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;

class CampaignCostCalculator
{
    /**
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string}
     */
    public function estimate(Campaign $campaign, ?string $category = 'MARKETING'): array
    {
        $recipients = max(0, (int) $campaign->total_recipients);
        $currency = (string) config('campaigns.cost.currency', 'INR');
        $rates = (array) config('campaigns.cost.category_rates', []);
        $catKey = strtoupper((string) ($category ?? 'MARKETING'));
        $unitCost = (float) ($rates[$catKey] ?? $rates['DEFAULT'] ?? 0.78);

        return [
            'recipients' => $recipients,
            'unit_cost' => $unitCost,
            'total_cost' => round($unitCost * $recipients, 2),
            'currency' => $currency,
        ];
    }
}
