<?php

declare(strict_types=1);

namespace App\Contracts;

interface OutboundTemplateSenderInterface
{
    /**
     * @param array<string, mixed> $templateParams
     * @return array{success: bool, message_id: string|null, error: string|null}
     */
    public function sendTemplate(
        int $whatsappLineId,
        string $contactPhone,
        string $templateCode,
        array $templateParams = [],
        ?string $language = 'en_US',
    ): array;
}
