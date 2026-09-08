<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Models\Message;

class MessageObserver
{
    public function __construct(
        private readonly WhatsappLineRegistryService $registryService,
    ) {}

    public function created(Message $message): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $externalId = $message->external_message_id;
        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '' || ! is_string($externalId) || $externalId === '') {
            return;
        }

        $this->registryService->indexMessage($tenantId, $externalId, (int) $message->id);
    }
}
