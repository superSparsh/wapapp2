<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Domains\Billing\Services\WalletAutoRechargeService;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckWalletAutoRechargeCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'wallet:check-auto-recharge {--tenants=* : Tenant IDs to process}';

    protected $description = 'Check wallet balances and trigger auto-recharge when below threshold.';

    public function handle(WalletAutoRechargeService $service): int
    {
        $triggered = 0;
        $failedTenants = 0;

        $this->foreachTenant(function () use ($service, &$triggered, &$failedTenants): void {
            try {
                if (! Schema::hasTable('wallet_auto_recharge_settings')) {
                    return;
                }

                if ($service->processIfNeeded()) {
                    $triggered++;
                }
            } catch (Throwable $e) {
                $failedTenants++;
                $this->error('Wallet auto-recharge failed for tenant: '.$e->getMessage());
                report($e);
            }
        });

        $this->info("Triggered {$triggered} auto-recharge order(s).");

        return $failedTenants > 0 ? self::FAILURE : self::SUCCESS;
    }
}
