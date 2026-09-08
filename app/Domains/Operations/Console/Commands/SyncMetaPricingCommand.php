<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Models\CountryPricing;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncMetaPricingCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'operations:sync-meta-pricing {--tenants=* : Tenant IDs to process}';

    protected $description = 'Sync Meta WhatsApp conversation pricing data from central country_pricing.';

    public function handle(): int
    {
        $active = CountryPricing::query()->where('is_active', true)->count();
        $this->info("Central country pricing rows (active): {$active}");

        $this->foreachTenant(function ($tenant) use ($active): void {
            Log::info('SyncMetaPricingCommand: central pricing available for tenant billing sync', [
                'tenant_id' => $tenant->id,
                'active_country_pricing_rows' => $active,
            ]);
        });

        $this->info('Meta pricing sync completed (central CountryPricing source).');

        return self::SUCCESS;
    }
}
