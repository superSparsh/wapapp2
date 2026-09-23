<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Billing\Services\TemplateWalletChargeService;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Models\WalletTransaction;

/**
 * @deprecated Prefer TemplateWalletChargeService — kept as a thin campaign-facing wrapper.
 */
class CampaignWalletChargeService
{
    public function __construct(
        private readonly TemplateWalletChargeService $templateWalletChargeService,
    ) {}

    public function chargeIfDelivered(
        Message $message,
        string $deliveryStatus,
        ?CampaignRecipient $recipient = null,
    ): ?WalletTransaction {
        return $this->templateWalletChargeService->chargeIfDelivered(
            $message,
            $deliveryStatus,
            $recipient,
        );
    }
}
