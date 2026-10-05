<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Resolves which tenant owns an Alibaba status callback before tenancy is initialized.
 */
class WebhookTenantResolver
{
    public function __construct(
        private readonly WhatsappLineRegistryService $registryService,
    ) {}

    /**
     * @param  array<string, mixed>  $item  Normalized Alibaba status payload item
     */
    public function resolveForStatusCallback(string $providerMessageId, array $item): ?Tenant
    {
        $providerMessageId = trim($providerMessageId);
        if ($providerMessageId === '') {
            return null;
        }

        $tenant = $this->registryService->resolveTenantByExternalMessageId($providerMessageId);
        if ($tenant !== null) {
            return $tenant;
        }

        foreach ($this->alternateProviderIds($item) as $alternateId) {
            $tenant = $this->registryService->resolveTenantByExternalMessageId($alternateId);
            if ($tenant !== null) {
                return $tenant;
            }
        }

        foreach ($this->businessPhonesFromStatusItem($item) as $phone) {
            $resolved = $this->registryService->resolveByBusinessPhone($phone);
            if ($resolved !== null) {
                return $resolved['tenant'];
            }
        }

        $tenant = $this->resolveByScanningTenantDatabases($providerMessageId);
        if ($tenant !== null) {
            Log::info('Status webhook tenant resolved by scanning tenant databases (index was missing)', [
                'provider_message_id' => $providerMessageId,
                'tenant_id' => $tenant->id,
            ]);
        }

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function alternateProviderIds(array $item): array
    {
        $candidates = [
            $item['GroupId'] ?? null,
            $item['groupId'] ?? null,
            $item['TaskId'] ?? null,
            $item['taskId'] ?? null,
        ];

        $ids = [];
        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }
            $id = trim((string) $candidate);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function businessPhonesFromStatusItem(array $item): array
    {
        $phones = [];
        foreach ([
            'From',
            'from',
            'OriginPhoneNumber',
            'originPhoneNumber',
            'origin_phone_number',
        ] as $key) {
            $value = $item[$key] ?? null;
            if (! is_scalar($value)) {
                continue;
            }
            $phone = trim((string) $value);
            if ($phone !== '') {
                $phones[] = $phone;
            }
        }

        return array_values(array_unique($phones));
    }

    private function resolveByScanningTenantDatabases(string $providerMessageId): ?Tenant
    {
        foreach (Tenant::query()->cursor() as $tenant) {
            if (! $tenant instanceof Tenant) {
                continue;
            }

            try {
                tenancy()->initialize($tenant);

                $message = Message::query()
                    ->where('external_message_id', $providerMessageId)
                    ->first();

                if (! $message instanceof Message) {
                    continue;
                }

                $this->registryService->indexMessage(
                    (string) $tenant->id,
                    $providerMessageId,
                    (int) $message->id,
                );

                return Tenant::query()->find($tenant->id);
            } finally {
                tenancy()->end();
            }
        }

        return null;
    }
}
