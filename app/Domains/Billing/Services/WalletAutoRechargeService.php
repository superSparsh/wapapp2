<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\WalletAutoRechargeSetting;

class WalletAutoRechargeService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function settings(): WalletAutoRechargeSetting
    {
        return WalletAutoRechargeSetting::query()->firstOrCreate([], [
            'enabled' => false,
            'threshold_amount' => (float) config('billing.wallet.auto_recharge_threshold', 500),
            'recharge_amount' => (float) config('billing.wallet.auto_recharge_amount', 5000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSettings(array $data): WalletAutoRechargeSetting
    {
        $settings = $this->settings();
        $settings->fill($data)->save();

        return $settings->fresh() ?? $settings;
    }

    public function shouldRecharge(): bool
    {
        $settings = $this->settings();

        if (! $settings->enabled) {
            return false;
        }

        return $this->walletService->balance() <= (float) $settings->threshold_amount;
    }

    public function processIfNeeded(): bool
    {
        if (! $this->shouldRecharge()) {
            return false;
        }

        $settings = $this->settings();

        $this->walletService->createRechargeOrder((float) $settings->recharge_amount);

        $settings->forceFill(['last_triggered_at' => now()])->save();

        return true;
    }
}
