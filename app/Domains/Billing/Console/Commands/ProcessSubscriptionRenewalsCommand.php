<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Domains\Admin\Support\RespectsMaintenanceModules;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionRenewalsCommand extends Command
{
    use IteratesTenants;
    use RespectsMaintenanceModules;

    protected $signature = 'billing:process-subscription-renewals {--tenants=* : Tenant IDs to process}';

    protected $description = 'Process due subscription renewals for all tenants.';

    public function handle(): int
    {
        if ($this->skipForMaintenance('billing_jobs', 'Billing:')) {
            return self::SUCCESS;
        }

        $this->foreachTenant(function ($tenant): void {
            Log::info('ProcessSubscriptionRenewalsCommand: stub renewal check', [
                'tenant_id' => $tenant->id,
            ]);
        });

        $this->info('Subscription renewal processing completed (stub).');

        return self::SUCCESS;
    }
}
