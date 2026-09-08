<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Services;

class TenantDatabaseNamingService
{
    public function prefix(): string
    {
        return (string) config('tenancy.database.name_prefix', 'wapapp_tenant_');
    }

    public function forTenantId(string $tenantId): string
    {
        $normalized = strtolower($tenantId);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            throw new \InvalidArgumentException('Tenant ID cannot produce a valid database name.');
        }

        $name = $this->prefix().$normalized;

        if (strlen($name) > 64) {
            throw new \InvalidArgumentException('Tenant database name exceeds MySQL 64 character limit.');
        }

        return $name;
    }

    /**
     * @return list<string>
     */
    public function legacyDatabaseNames(string $tenantId): array
    {
        $prefix = (string) config('tenancy.database.prefix', 'tenant');
        $suffix = (string) config('tenancy.database.suffix', '');

        $names = [
            $prefix.$tenantId.$suffix,
        ];

        $base = explode('-', $tenantId, 2)[0];

        if ($base !== '' && $base !== $tenantId) {
            $names[] = $prefix.$base.$suffix;
        }

        return array_values(array_unique($names));
    }
}
