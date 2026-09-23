<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Models\Campaign;

class CampaignCostCalculator
{
    /**
     * Estimate template message cost for a campaign.
     *
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string}
     */
    public function estimate(Campaign $campaign): array
    {
        return $this->estimateFor(
            max(0, (int) $campaign->total_recipients),
            $campaign->template?->category,
        );
    }

    /**
     * @return array{recipients: int, unit_cost: float, total_cost: float, currency: string, category: string}
     */
    public function estimateFor(int $recipients, ?string $category): array
    {
        $recipients = max(0, $recipients);
        $normalized = strtoupper(trim((string) $category));
        $unitCost = $this->unitCostForCategory($normalized);
        $currency = (string) config('campaigns.cost.currency', 'INR');

        return [
            'recipients' => $recipients,
            'unit_cost' => $unitCost,
            'total_cost' => round($unitCost * $recipients, 2),
            'currency' => $currency,
            'category' => $normalized !== '' ? $normalized : 'DEFAULT',
        ];
    }

    public function unitCostForCategory(?string $category): float
    {
        $normalized = strtoupper(trim((string) $category));
        $rates = (array) config('campaigns.cost.category_rates', []);

        if ($normalized !== '' && isset($rates[$normalized])) {
            return (float) $rates[$normalized];
        }

        return (float) ($rates['DEFAULT'] ?? 0.78);
    }
}
