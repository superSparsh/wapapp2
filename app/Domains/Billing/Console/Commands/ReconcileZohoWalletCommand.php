<?php

declare(strict_types=1);

namespace App\Domains\Billing\Console\Commands;

use App\Domains\Billing\Jobs\ProcessWalletRazorpayZohoInvoiceJob;
use App\Domains\Billing\Services\Zoho\ZohoBooksWalletCreditService;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Models\RazorpayOrder;
use App\Models\WalletTransaction;
use App\Models\ZohoWalletCreditRequest;
use App\Support\Console\Concerns\IteratesTenants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Retry Razorpay→Zoho wallet invoice sync + completion emails for paid recharges.
 * (Legacy wallet:zoho-reconcile for invoice-first flow; here we heal the live Razorpay path.)
 */
class ReconcileZohoWalletCommand extends Command
{
    use IteratesTenants;

    protected $signature = 'billing:reconcile-zoho-wallet
        {--tenants=* : Tenant IDs to process}
        {--limit=50 : Max rows to retry per tenant}';

    protected $description = 'Retry failed/pending Zoho wallet invoices for paid Razorpay recharges.';

    public function handle(ZohoBooksWalletCreditService $zohoBooks): int
    {
        if (! $zohoBooks->isConfigured()) {
            $this->warn('Zoho Books is not configured; skipping wallet reconcile.');

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $dispatched = 0;

        $this->foreachTenant(function ($tenant) use ($limit, &$dispatched): void {
            $count = $this->reconcileTenant((string) $tenant->id, $limit);
            $dispatched += $count;

            if ($count > 0) {
                $this->line("Tenant {$tenant->id}: queued {$count} Zoho wallet sync job(s).");
            }
        });

        $this->info("Zoho wallet reconciliation complete. Queued {$dispatched} job(s).");

        return self::SUCCESS;
    }

    private function reconcileTenant(string $tenantId, int $limit): int
    {
        $queued = 0;
        $seenOrderIds = [];

        // 1) Central ledger rows that failed / never synced / never emailed.
        $ledgerRows = ZohoWalletCreditRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('source', 'razorpay')
            ->where(function ($q): void {
                $q->whereIn('status', ['pending', 'failed'])
                    ->orWhereNull('completion_email_sent_at');
            })
            ->whereNotNull('external_id')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($ledgerRows as $row) {
            if ($row->status === 'synced'
                && filled($row->invoice_number)
                && $row->completion_email_sent_at !== null) {
                continue;
            }

            $orderId = (int) data_get($row->metadata, 'local_order_id', 0);
            $paymentId = (string) $row->external_id;

            if ($orderId < 1) {
                $orderId = $this->resolveLocalOrderId($paymentId);
            }

            if ($orderId < 1 || $paymentId === '') {
                Log::warning('ReconcileZohoWallet: missing order/payment for ledger row', [
                    'tenant_id' => $tenantId,
                    'credit_request_id' => $row->id,
                ]);

                continue;
            }

            ProcessWalletRazorpayZohoInvoiceJob::dispatch($tenantId, $orderId, $paymentId);
            $seenOrderIds[$orderId] = true;
            $queued++;
        }

        // 2) Paid Razorpay wallet orders with no synced ledger row yet.
        $remaining = $limit - $queued;
        if ($remaining <= 0) {
            return $queued;
        }

        $paidOrders = RazorpayOrder::query()
            ->where('purpose', RazorpayOrderPurpose::WalletRecharge)
            ->where('status', RazorpayOrderStatus::Paid)
            ->whereNotNull('razorpay_payment_id')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        foreach ($paidOrders as $order) {
            if ($remaining <= 0) {
                break;
            }

            $orderId = (int) $order->id;
            if (isset($seenOrderIds[$orderId])) {
                continue;
            }

            $paymentId = (string) $order->razorpay_payment_id;
            $synced = ZohoWalletCreditRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('source', 'razorpay')
                ->where('external_id', $paymentId)
                ->where('status', 'synced')
                ->whereNotNull('completion_email_sent_at')
                ->exists();

            if ($synced) {
                continue;
            }

            ProcessWalletRazorpayZohoInvoiceJob::dispatch($tenantId, $orderId, $paymentId);
            $seenOrderIds[$orderId] = true;
            $queued++;
            $remaining--;
        }

        return $queued;
    }

    private function resolveLocalOrderId(string $paymentId): int
    {
        $txn = WalletTransaction::query()
            ->where('razorpay_payment_id', $paymentId)
            ->where('reference_type', RazorpayOrder::class)
            ->latest('id')
            ->first();

        if ($txn?->reference_id) {
            return (int) $txn->reference_id;
        }

        $order = RazorpayOrder::query()
            ->where('razorpay_payment_id', $paymentId)
            ->first();

        return (int) ($order?->id ?? 0);
    }
}
