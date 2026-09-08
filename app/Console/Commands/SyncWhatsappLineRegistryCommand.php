<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Models\Tenant;
use App\Models\WhatsappLine;
use Illuminate\Console\Command;

class SyncWhatsappLineRegistryCommand extends Command
{
    protected $signature = 'inbox:sync-line-registry {--tenant= : Sync a single tenant id}';

    protected $description = 'Sync tenant WhatsApp lines into the central line registry for inbound webhooks';

    public function handle(WhatsappLineRegistryService $registryService): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::query()->where('id', $tenantId)->get()
            : Tenant::query()->get();

        $synced = 0;

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);

            WhatsappLine::query()->each(function (WhatsappLine $line) use ($registryService, $tenant, &$synced): void {
                $registryService->syncLine($tenant->id, (int) $line->id, $line->phone);
                $synced++;
            });

            tenancy()->end();
        }

        $this->info("Synced {$synced} WhatsApp line(s) to central registry.");

        return self::SUCCESS;
    }
}
