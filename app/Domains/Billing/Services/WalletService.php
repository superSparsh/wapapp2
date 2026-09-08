<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Enums\WalletTransactionType;
use App\Domains\Dashboard\Services\DashboardService;
use App\Models\RazorpayOrder;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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

        return WalletTransaction::query()
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

    public function completeRecharge(RazorpayOrder $order, string $paymentId): WalletTransaction
    {
        return DB::transaction(function () use ($order, $paymentId): WalletTransaction {
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
    }
}
