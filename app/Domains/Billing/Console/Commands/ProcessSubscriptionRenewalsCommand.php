<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewalsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'billing:process-subscription-renewals {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process due subscription renewals for all tenants.';

    public function handle(): int
    {
        $this->foreachTenant(function ($tenant): void {
            Log::info('ProcessSubscriptionRenewalsCommand: stub renewal check', [
                'tenant_id' => $tenant->id,
            ]);
        });

        $this->info('Subscription renewal processing completed (stub).');

        return self::SUCCESS;
    }
}
