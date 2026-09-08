<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Domains\Billing\Services\WalletAutoRechargeService;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;

class CheckWalletAutoRechargeCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'wallet:check-auto-recharge {--tenants=* : Tenant IDs to process}';

    protected $description = 'Check wallet balances and trigger auto-recharge when below threshold.';

    public function handle(WalletAutoRechargeService $service): int
    {
        $triggered = 0;

        $this->foreachTenant(function () use ($service, &$triggered): void {
            if ($service->processIfNeeded()) {
                $triggered++;
            }
        });

        $this->info("Triggered {$triggered} auto-recharge order(s).");

        return self::SUCCESS;
    }
}
