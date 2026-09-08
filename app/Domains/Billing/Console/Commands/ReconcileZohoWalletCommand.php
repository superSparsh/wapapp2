<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileZohoWalletCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'billing:reconcile-zoho-wallet {--tenants=* : Tenant IDs to process}';

    protected $description = 'Reconcile wallet balances with Zoho Books.';

    public function handle(): int
    {
        $this->foreachTenant(function ($tenant): void {
            Log::info('ReconcileZohoWalletCommand: stub reconcile', [
                'tenant_id' => $tenant->id,
            ]);
        });

        $this->info('Zoho wallet reconciliation completed (stub).');

        return self::SUCCESS;
    }
}
