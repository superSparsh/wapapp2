<?php

declare(strict_types=1);

namespace App\Services\Outbound;

use App\Contracts\OutboundTemplateSenderInterface;
use Illuminate\Support\Str;

class LocalTemplateSender implements OutboundTemplateSenderInterface
{
    public function sendTemplate(
        int $whatsappLineId,
        string $contactPhone,
        string $templateCode,
        array $templateParams = [],
        ?string $language = 'en_US',
    ): array {
        // Mock / local outbound template sender (for unit testing and local development)
        return [
            'success' => true,
            'message_id' => 'msg_loc_' . Str::random(16),
            'error' => null,
        ];
    }
}
