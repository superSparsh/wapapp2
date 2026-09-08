<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\OutboundTemplateSenderInterface;
use App\Models\Campaign;
use App\Support\PhoneNormalizer;

class CampaignTestMessageService
{
    public function __construct(
        private readonly OutboundTemplateSenderInterface $templateSender,
    ) {}

    /**
     * @param array<string, mixed> $templateVariables
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    public function send(Campaign $campaign, string $phone, array $templateVariables = []): array
    {
        $lineId = $campaign->whatsapp_line_id;
        if ($lineId === null) {
            abort(422, 'WhatsApp line is required for test message.');
        }

        $normalized = PhoneNormalizer::normalize($phone) ?? $phone;
        $templateCode = (string) ($campaign->template_variables['template_code'] ?? 'template_' . $campaign->template_id);
        $params = $templateVariables ?: (array) ($campaign->template_variables ?? []);

        return $this->templateSender->sendTemplate(
            whatsappLineId: (int) $lineId,
            contactPhone: $normalized,
            templateCode: $templateCode,
            templateParams: $params,
        );
    }
}
