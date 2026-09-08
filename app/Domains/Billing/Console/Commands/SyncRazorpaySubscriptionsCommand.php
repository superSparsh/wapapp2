<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncRazorpaySubscriptionsCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'billing:sync-razorpay-subscriptions {--tenants=* : Tenant IDs to process}';

    protected $description = 'Sync Razorpay subscription statuses for all tenants.';

    public function handle(): int
    {
        $this->foreachTenant(function ($tenant): void {
            Log::info('SyncRazorpaySubscriptionsCommand: stub sync', [
                'tenant_id' => $tenant->id,
            ]);
        });

        $this->info('Razorpay subscription sync completed (stub).');

        return self::SUCCESS;
    }
}
