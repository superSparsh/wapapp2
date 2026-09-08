<?php

declare(strict_types=1);

namespace App\Domains\Operations\Console\Commands;

use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncFreeUicQuotaCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'operations:sync-free-uic-quota {--tenants=* : Tenant IDs to process}';

    protected $description = 'Sync free UIC quota usage from Meta.';

    public function handle(): int
    {
        $this->foreachTenant(function ($tenant): void {
            Log::info('SyncFreeUicQuotaCommand: stub sync', [
                'tenant_id' => $tenant->id,
            ]);
        });

        $this->info('Free UIC quota sync completed (stub).');

        return self::SUCCESS;
    }
}
