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
        ?string $fromDate = null,
        ?string $toDate = null,
    ): LengthAwarePaginator {
        [$from, $to] = $this->resolveHistoryRange($period, $fromDate, $toDate);
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
                        ->orWhere('metadata->legacy_category', 'like', "%{$search}%")
                        ->orWhere('metadata->legacy_campaign_id', 'like', "%{$search}%")
                        ->orWhere('metadata->template_category', 'like', "%{$search}%")
                        ->orWhere('metadata->campaign_id', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $this->attachDisplayBalanceAfter($paginator);

        return $paginator;
    }

    /**
     * Stream wallet history CSV for the same filters as the history page.
     */
    public function exportTransactionsCsv(
        ?string $search = null,
        ?string $period = null,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        [$from, $to] = $this->resolveHistoryRange($period, $fromDate, $toDate);
        $search = trim((string) $search);
        $filename = 'wallet-history-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($from, $to, $search): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SI. No',
                'Description',
                'Type',
                'Date',
                'Amount',
                'Balance After',
                'Payment ID',
                'Campaign ID',
                'Category',
            ]);

            $seq = 0;
            $query = WalletTransaction::query()
                ->whereBetween('created_at', [$from, $to])
                ->when($search !== '', function ($q) use ($search): void {
                    $q->where(function ($nested) use ($search): void {
                        $nested->where('description', 'like', "%{$search}%")
                            ->orWhere('razorpay_payment_id', 'like', "%{$search}%")
                            ->orWhere('metadata->legacy_category', 'like', "%{$search}%")
                            ->orWhere('metadata->legacy_campaign_id', 'like', "%{$search}%")
                            ->orWhere('metadata->template_category', 'like', "%{$search}%")
                            ->orWhere('metadata->campaign_id', 'like', "%{$search}%");
                    });
                })
                ->latest('id');

            $liveBalance = $this->balance();
            $runningNewerNet = 0.0;

            $query->cursor()->each(function (WalletTransaction $transaction) use ($handle, &$seq, $liveBalance, &$runningNewerNet): void {
                $balanceAfter = round($liveBalance - $runningNewerNet, 2);
                $signed = $transaction->type === WalletTransactionType::Credit
                    ? abs((float) $transaction->amount)
                    : -abs((float) $transaction->amount);
                $runningNewerNet += $signed;

                $meta = is_array($transaction->metadata) ? $transaction->metadata : [];
                $description = $transaction->description
                    ?: ($meta['legacy_category'] ?? null)
                    ?: ($meta['legacy_type'] ?? null)
                    ?: ($transaction->type === WalletTransactionType::Credit ? 'Wallet credit' : 'Wallet withdrawal');

                fputcsv($handle, [
                    ++$seq,
                    $description,
                    $transaction->type?->label() ?? '—',
                    $transaction->created_at?->format('d M Y h:i:s A') ?? 'N/A',
                    ($transaction->type?->signPrefix() ?? '').number_format((float) $transaction->amount, 2, '.', ''),
                    number_format($balanceAfter, 2, '.', ''),
                    $transaction->razorpay_payment_id ?: 'N/A',
                    (string) ($meta['legacy_campaign_id'] ?? $transaction->reference_id ?? 'N/A'),
                    (string) ($meta['legacy_category'] ?? 'N/A'),
                ]);
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function resolveHistoryRange(?string $period, ?string $fromDate, ?string $toDate): array
    {
        $maxDays = (int) config('billing.wallet.history_max_days', 365);
        $historyCutoff = now()->subDays($maxDays)->startOfDay();

        $fromInput = filled($fromDate) ? \Illuminate\Support\Carbon::parse($fromDate)->startOfDay() : null;
        $toInput = filled($toDate) ? \Illuminate\Support\Carbon::parse($toDate)->endOfDay() : null;

        if ($fromInput !== null || $toInput !== null) {
            $to = $toInput ?? now()->endOfDay();
            $from = $fromInput ?? $to->copy()->subDays($maxDays)->startOfDay();
            if ($from->greaterThan($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
            if ($from->lessThan($historyCutoff)) {
                $from = $historyCutoff->copy();
            }

            return [$from, $to];
        }

        $period = DashboardService::normalizePeriod($period, DashboardService::PERIOD_ALL);
        [$from, $to] = DashboardService::periodRange($period, $maxDays);
        if ($from->lessThan($historyCutoff)) {
            $from = $historyCutoff->copy();
        }

        return [$from, $to];
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

    /**
     * Debit the current tenant wallet.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function debit(
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $metadata = [],
        ?string $idempotencyKey = null,
        bool $allowNegative = true,
    ): WalletTransaction {
        $amount = round(abs($amount), 4);
        abort_unless($amount > 0, 422, 'Debit amount must be greater than zero.');

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $metadata['idempotency_key'] = $idempotencyKey;

            $existing = WalletTransaction::query()
                ->where('type', WalletTransactionType::Debit)
                ->where('metadata->idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof WalletTransaction) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($amount, $description, $referenceType, $referenceId, $metadata, $allowNegative, $idempotencyKey): WalletTransaction {
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = WalletTransaction::query()
                    ->where('type', WalletTransactionType::Debit)
                    ->where('metadata->idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof WalletTransaction) {
                    return $existing;
                }
            }

            $wallet = WalletAccount::query()->lockForUpdate()->first();
            if ($wallet === null) {
                $wallet = $this->account();
                $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $current = round((float) $wallet->balance, 4);
            $roundedAmount = round($amount, 2);

            if (! $allowNegative && $current < $roundedAmount) {
                abort(422, 'Insufficient wallet balance.');
            }

            $newBalance = round($current - $roundedAmount, 2);
            $wallet->update(['balance' => $newBalance]);

            return WalletTransaction::query()->create([
                'type' => WalletTransactionType::Debit,
                'amount' => $roundedAmount,
                'currency' => $wallet->currency ?? 'INR',
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        });
    }

    public function createRechargeOrder(float $amount): RazorpayOrder
    {
        $min = (float) config('billing.wallet.min_recharge_amount', 500);
        $max = (float) config('billing.wallet.max_recharge_amount', 500000);
        abort_unless($amount >= $min && $amount <= $max, 422, "Recharge amount must be between {$min} and {$max}.");

        $subscriptionService = app(SubscriptionService::class);
        $totals = $subscriptionService->calculateTotals($amount);

        $order = DB::transaction(function () use ($amount, $totals): RazorpayOrder {
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

        try {
            $user = Auth::user();
            app(\App\Domains\Alerts\Services\AlertDispatcher::class)->walletCreditRequested([
                'customer_email' => $user instanceof \App\Models\User ? $user->email : null,
                'customer_name' => $user instanceof \App\Models\User ? $user->name : null,
                'amount' => $amount,
                'currency' => 'INR',
                'reference' => $order->razorpay_order_id,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Wallet credit requested alert failed', ['error' => $e->getMessage()]);
        }

        return $order;
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
        $wasAlreadyPaid = $order->status === RazorpayOrderStatus::Paid;

        $transaction = DB::transaction(function () use ($order, $paymentId): WalletTransaction {
            $locked = RazorpayOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === RazorpayOrderStatus::Paid) {
                $existing = WalletTransaction::query()
                    ->where('razorpay_payment_id', $paymentId)
                    ->where('reference_type', RazorpayOrder::class)
                    ->where('reference_id', $locked->id)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $this->razorpayService->markOrderPaid($locked, $paymentId);

            $wallet = $this->account();
            $creditAmount = round((float) $locked->amount, 2);
            $taxAmount = round((float) ($locked->tax_amount ?? 0), 2);
            $payable = round((float) ($locked->total_amount ?? ($creditAmount + $taxAmount)), 2);
            $newBalance = round((float) $wallet->balance + $creditAmount, 2);
            $wallet->update(['balance' => $newBalance]);

            $description = 'Razorpay payment '.$paymentId
                .' (credits ₹'.number_format($creditAmount, 2)
                .', GST ₹'.number_format($taxAmount, 2)
                .', paid ₹'.number_format($payable, 2).')';

            return WalletTransaction::query()->create([
                'type' => WalletTransactionType::Credit,
                'amount' => $creditAmount,
                'currency' => $locked->currency,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => RazorpayOrder::class,
                'reference_id' => $locked->id,
                'razorpay_payment_id' => $paymentId,
                'created_at' => now(),
            ]);
        });

        $tenantId = tenant('id');
        if ($tenantId && ! $wasAlreadyPaid) {
            $userId = Auth::id();
            $orderId = (int) $order->id;
            DB::afterCommit(function () use ($tenantId, $orderId, $paymentId, $userId): void {
                ProcessWalletRazorpayZohoInvoiceJob::dispatch(
                    (string) $tenantId,
                    $orderId,
                    $paymentId,
                    $userId !== null ? (int) $userId : null,
                );
            });
        }

        return $transaction;
    }
}
