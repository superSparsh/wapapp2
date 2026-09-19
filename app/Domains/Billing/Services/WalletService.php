<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Jobs\ProcessWalletRazorpayZohoInvoiceJob;
use App\Domains\Dashboard\Services\DashboardService;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\RazorpayOrder;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(
        private readonly RazorpayService $razorpayService,
    ) {}

    public function account(): WalletAccount
    {
        return WalletAccount::query()->firstOrCreate([], [
            'balance' => 0,
            'currency' => 'INR',
        ]);
    }

    public function balance(): float
    {
        $balance = WalletAccount::query()->value('balance');

        if ($balance !== null) {
            return (float) $balance;
        }

        return (float) $this->account()->balance;
    }

    public function paginateTransactions(
        ?string $search = null,
        int $perPage = 25,
        ?string $period = null,
    ): LengthAwarePaginator {
        $maxDays = (int) config('billing.wallet.history_max_days', 365);
        $period = DashboardService::normalizePeriod($period, DashboardService::PERIOD_ALL);
        [$from, $to] = DashboardService::periodRange($period, $maxDays);
        $historyCutoff = now()->subDays($maxDays);
        if ($from->lessThan($historyCutoff)) {
            $from = $historyCutoff;
        }
        $search = trim((string) $search);

        $paginator = WalletTransaction::query()
            ->select([
                'id',
                'uuid',
                'type',
                'amount',
                'currency',
                'description',
                'metadata',
                'balance_after',
                'razorpay_payment_id',
                'reference_type',
                'reference_id',
                'created_at',
            ])
            ->whereBetween('created_at', [$from, $to])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('description', 'like', "%{$search}%")
                        ->orWhere('razorpay_payment_id', 'like', "%{$search}%")
                        ->orWhere('metadata->legacy_category', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $this->attachDisplayBalanceAfter($paginator);

        return $paginator;
    }

    /**
     * Remaining wallet after each row, walked back from the live balance.
     * Stored balance_after can be wrong for migrated ledgers (rebuilt from 0).
     */
    private function attachDisplayBalanceAfter(LengthAwarePaginator $paginator): void
    {
        $items = $paginator->getCollection();
        if ($items->isEmpty()) {
            return;
        }

        $current = $this->balance();
        $ids = $items->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $connection = (new WalletTransaction)->getConnection();

        $nets = collect($connection->select(
            "SELECT t.id AS id, COALESCE((
                SELECT SUM(CASE WHEN n.type = 'credit' THEN ABS(n.amount) ELSE -ABS(n.amount) END)
                FROM wallet_transactions AS n
                WHERE n.id > t.id
            ), 0) AS newer_net
            FROM wallet_transactions AS t
            WHERE t.id IN ({$placeholders})",
            $ids,
        ))->keyBy(fn (object $row): int => (int) $row->id);

        foreach ($items as $transaction) {
            $newerNet = (float) ($nets[(int) $transaction->id]->newer_net ?? 0);
            $transaction->setAttribute('display_balance_after', round($current - $newerNet, 2));
        }
    }

    /** @return Collection<int, WalletTransaction> */
    public function recentTransactions(int $limit = 20): Collection
    {
        return WalletTransaction::query()->latest('id')->limit($limit)->get();
    }

    public function createRechargeOrder(float $amount): RazorpayOrder
    {
        $min = (float) config('billing.wallet.min_recharge_amount', 500);
        $max = (float) config('billing.wallet.max_recharge_amount', 500000);
        abort_unless($amount >= $min && $amount <= $max, 422, "Recharge amount must be between {$min} and {$max}.");

        $subscriptionService = app(SubscriptionService::class);
        $totals = $subscriptionService->calculateTotals($amount);

        return DB::transaction(function () use ($amount, $totals): RazorpayOrder {
            $razorpayOrder = $this->razorpayService->createOrder(
                amount: $totals['total'],
                currency: 'INR',
                purpose: RazorpayOrderPurpose::WalletRecharge,
                notes: ['recharge_amount' => $amount],
            );

            return RazorpayOrder::query()->create([
                'razorpay_order_id' => $razorpayOrder['id'],
                'purpose' => RazorpayOrderPurpose::WalletRecharge,
                'amount' => $totals['amount'],
                'tax_amount' => $totals['tax'],
                'total_amount' => $totals['total'],
                'currency' => 'INR',
                'status' => RazorpayOrderStatus::Created,
                'metadata' => ['razorpay' => $razorpayOrder],
            ]);
        });
    }

    /**
     * Credit the current tenant wallet without a payment gateway order.
     */
    public function adminCredit(float $amount, string $description = 'Admin wallet credit'): WalletTransaction
    {
        abort_unless($amount > 0, 422, 'Credit amount must be greater than zero.');

        return DB::transaction(function () use ($amount, $description): WalletTransaction {
            $wallet = $this->account();
            $newBalance = (float) $wallet->balance + $amount;
            $wallet->update(['balance' => $newBalance]);

            return WalletTransaction::query()->create([
                'type' => WalletTransactionType::Credit,
                'amount' => $amount,
                'currency' => $wallet->currency ?? 'INR',
                'balance_after' => $newBalance,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }

    public function completeRecharge(RazorpayOrder $order, string $paymentId): WalletTransaction
    {
        $transaction = DB::transaction(function () use ($order, $paymentId): WalletTransaction {
            $this->razorpayService->markOrderPaid($order, $paymentId);

            $wallet = $this->account();
            $newBalance = (float) $wallet->balance + (float) $order->amount;
            $wallet->update(['balance' => $newBalance]);

            return WalletTransaction::query()->create([
                'type' => WalletTransactionType::Credit,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'balance_after' => $newBalance,
                'description' => 'Wallet recharge via Razorpay',
                'reference_type' => RazorpayOrder::class,
                'reference_id' => $order->id,
                'razorpay_payment_id' => $paymentId,
                'created_at' => now(),
            ]);
        });

        $tenantId = tenant('id');
        if ($tenantId) {
            $userId = Auth::id();
            DB::afterCommit(function () use ($tenantId, $order, $paymentId, $userId): void {
                ProcessWalletRazorpayZohoInvoiceJob::dispatch(
                    (string) $tenantId,
                    (int) $order->id,
                    $paymentId,
                    $userId !== null ? (int) $userId : null,
                );
            });
        }

        return $transaction;
    }
}
