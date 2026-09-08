<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Services;

use App\Models\WhatsappFlowExchangeRegistry;
use App\Models\WhatsappFlow;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant;

class WhatsappFlowExchangeRegistryService
{
    public function register(string $token, Tenant $tenant, WhatsappFlow $flow): void
    {
        if (! $this->tableExists()) {
            return;
        }

        WhatsappFlowExchangeRegistry::query()->updateOrCreate(
            ['exchange_token' => $token],
            [
                'tenant_id' => $tenant->getTenantKey(),
                'flow_id' => $flow->id,
            ],
        );
    }

    public function remove(string $token): void
    {
        if (! $this->tableExists()) {
            return;
        }

        WhatsappFlowExchangeRegistry::query()
            ->where('exchange_token', $token)
            ->delete();
    }

    /**
     * @return array{tenant: Tenant, flow_id: int}|null
     */
    public function resolve(string $token): ?array
    {
        if (! $this->tableExists()) {
            return null;
        }

        $entry = WhatsappFlowExchangeRegistry::query()
            ->where('exchange_token', $token)
            ->first();

        if ($entry === null) {
            return null;
        }

        $tenant = \App\Models\Tenant::query()->find($entry->tenant_id);

        if ($tenant === null) {
            return null;
        }

        return [
            'tenant' => $tenant,
            'flow_id' => (int) $entry->flow_id,
        ];
    }

    private function tableExists(): bool
    {
        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        return Schema::connection($connection)->hasTable('whatsapp_flow_exchange_registry');
    }
}
