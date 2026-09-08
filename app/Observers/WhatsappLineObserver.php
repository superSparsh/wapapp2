<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Models\Message;
use App\Models\WhatsappLine;

class WhatsappLineObserver
{
    public function __construct(
        private readonly WhatsappLineRegistryService $registryService,
    ) {}

    public function saved(WhatsappLine $line): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        $this->registryService->syncLine($tenantId, (int) $line->id, $line->phone);
    }

    public function deleted(WhatsappLine $line): void
    {
        $this->registryService->removeLine($line->phone);
    }
}
