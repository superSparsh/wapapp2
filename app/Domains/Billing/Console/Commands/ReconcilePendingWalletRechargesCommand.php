<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Domains\Billing\Services\RazorpayService;
use App\Domains\Billing\Services\WalletService;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Models\RazorpayOrder;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Backup for browser verify-payment misses (UPI/QR/tab close).
 * Only heals wallet_recharge orders created after the configured cutoff —
 * older stuck "created" rows are left alone for manual review.
 */
class ReconcilePendingWalletRechargesCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'billing:reconcile-pending-wallet-recharges
        {--tenants=* : Tenant IDs to process}
        {--limit=20 : Max created orders to check per tenant}';

    protected $description = 'Credit wallet for Razorpay wallet recharges that are paid on Razorpay but still created locally.';

    public function handle(RazorpayService $razorpay, WalletService $walletService): int
    {
        if (! $razorpay->isConfigured()) {
            $this->warn('Razorpay is not configured; skipping pending wallet recharge reconcile.');

            return self::SUCCESS;
        }

        $cutoff = $this->reconcileCutoff();
        if ($cutoff === null) {
            $this->warn('WALLET_RAZORPAY_RECONCILE_AFTER is empty; refusing to scan old orders.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $credited = 0;
        $checked = 0;
        $failed = 0;

        $this->foreachTenant(function ($tenant) use ($razorpay, $walletService, $cutoff, $limit, &$credited, &$checked, &$failed): void {
            $orders = RazorpayOrder::query()
                ->where('purpose', RazorpayOrderPurpose::WalletRecharge)
                ->where('status', RazorpayOrderStatus::Created)
                ->where('created_at', '>=', $cutoff)
                ->orderBy('id')
                ->limit($limit)
                ->get();

            foreach ($orders as $order) {
                $checked++;

                try {
                    if ($this->tryCompletePaidOrder($order, $razorpay, $walletService)) {
                        $credited++;
                        $this->line("Tenant {$tenant->id}: credited order {$order->razorpay_order_id}");
                    }
                } catch (Throwable $e) {
                    $failed++;
                    Log::warning('Pending wallet recharge reconcile failed', [
                        'tenant_id' => $tenant->id,
                        'razorpay_order_id' => $order->razorpay_order_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Pending wallet recharge reconcile: checked {$checked}, credited {$credited}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function tryCompletePaidOrder(
        RazorpayOrder $order,
        RazorpayService $razorpay,
        WalletService $walletService,
    ): bool {
        $paymentId = $razorpay->findCapturedPaymentIdForOrder((string) $order->razorpay_order_id);
        if ($paymentId === null) {
            return false;
        }

        $razorpay->assertPaymentMatchesOrder($order, $paymentId);
        $walletService->completeRecharge($order, $paymentId);

        return true;
    }

    private function reconcileCutoff(): ?Carbon
    {
        $raw = trim((string) config('billing.wallet.reconcile_created_after', ''));
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, config('app.timezone', 'UTC'));
        } catch (Throwable) {
            return null;
        }
    }
}
