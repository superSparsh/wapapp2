<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Models\MessageExternalIndex;
use App\Models\Tenant;
use App\Models\WhatsappLineRegistry;
use App\Support\PhoneNormalizer;

class WhatsappLineRegistryService
{
    public function syncLine(string $tenantId, int $lineId, string $phone): void
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === null) {
            return;
        }

        WhatsappLineRegistry::query()->updateOrCreate(
            ['phone' => $normalized],
            [
                'tenant_id' => $tenantId,
                'line_id' => $lineId,
            ],
        );
    }

    public function removeLine(string $phone): void
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === null) {
            return;
        }

        WhatsappLineRegistry::query()->where('phone', $normalized)->delete();
    }

    /**
     * @return array{tenant: Tenant, line_id: int}|null
     */
    public function resolveByBusinessPhone(?string $phone): ?array
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $registry = WhatsappLineRegistry::query()->where('phone', $normalized)->first();

        if ($registry === null) {
            foreach (PhoneNormalizer::lookupVariants($phone) as $variant) {
                $registry = WhatsappLineRegistry::query()->where('phone', $variant)->first();

                if ($registry !== null) {
                    break;
                }
            }
        }

        if ($registry === null) {
            return null;
        }

        $tenant = Tenant::query()->find($registry->tenant_id);

        if ($tenant === null) {
            return null;
        }

        return [
            'tenant' => $tenant,
            'line_id' => (int) $registry->line_id,
        ];
    }

    public function indexMessage(string $tenantId, string $externalMessageId, ?int $messageId = null): void
    {
        if ($externalMessageId === '') {
            return;
        }

        MessageExternalIndex::query()->updateOrCreate(
            ['external_message_id' => $externalMessageId],
            [
                'tenant_id' => $tenantId,
                'message_id' => $messageId,
                'created_at' => now(),
            ],
        );
    }

    public function resolveTenantByExternalMessageId(string $externalMessageId): ?Tenant
    {
        $index = MessageExternalIndex::query()
            ->where('external_message_id', $externalMessageId)
            ->first();

        if ($index === null) {
            return null;
        }

        return Tenant::query()->find($index->tenant_id);
    }
}
