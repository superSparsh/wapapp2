<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Admin\Support\AdminSession;
use App\Domains\Admin\Support\AdminViewAccess;
use App\Domains\Billing\Jobs\ProcessWalletRazorpayZohoInvoiceJob;
use App\Domains\Dashboard\Services\DashboardService;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Enums\WalletTransactionType;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
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
        ?string $category = null,
    ): LengthAwarePaginator {
        [$from, $to] = $this->resolveHistoryRange($period, $fromDate, $toDate);
        $search = trim((string) $search);
        $categoryKey = $this->normalizeHistoryCategory($category);

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
            ->when($categoryKey !== null, fn ($query) => $this->applyHistoryCategoryFilter($query, $categoryKey))
            ->tap(fn ($query) => $this->applyVisibleHistoryScope($query))
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
        ?string $category = null,
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        [$from, $to] = $this->resolveHistoryRange($period, $fromDate, $toDate);
        $search = trim((string) $search);
        $categoryKey = $this->normalizeHistoryCategory($category);
        $filename = 'wallet-history-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($from, $to, $search, $categoryKey): void {
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
                ->when($categoryKey !== null, fn ($q) => $this->applyHistoryCategoryFilter($q, $categoryKey))
                ->tap(fn ($q) => $this->applyVisibleHistoryScope($q))
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
                    $transaction->type?->label() ?? '-',
                    $transaction->created_at?->format('d M Y h:i:s A') ?? 'N/A',
                    ($transaction->type?->signPrefix() ?? '').number_format((float) $transaction->amount, 2, '.', ''),
                    number_format($balanceAfter, 2, '.', ''),
                    $transaction->razorpay_payment_id ?: 'N/A',
                    (string) ($meta['legacy_campaign_id'] ?? $transaction->reference_id ?? 'N/A'),
                    (string) ($meta['pricing_category'] ?? $meta['template_category'] ?? $meta['legacy_category'] ?? 'N/A'),
                ]);
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, string>
     */
    public static function historyCategoryOptions(): array
    {
        return [
            'marketing' => 'Marketing',
            'utility' => 'Utility',
            'authentication' => 'Auth',
            'service' => 'Service',
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\WalletTransaction>  $query
     */
    private function applyVisibleHistoryScope($query): void
    {
        if (! $this->shouldHideOptInFromWalletHistory()) {
            return;
        }

        $query->whereNot(function ($nested): void {
            $nested->where('metadata->wallet_source', 'opt_in')
                ->orWhere('description', 'like', 'Opt-in message%')
                ->orWhere('metadata->legacy_category', 'Opt-in messages');
        });
    }

    private function shouldHideOptInFromWalletHistory(): bool
    {
        if (! (bool) config('billing.wallet.hide_opt_in_from_history', true)) {
            return false;
        }

        if (AdminSession::isImpersonating() || AdminViewAccess::canAccess()) {
            return false;
        }

        return true;
    }

    private function normalizeHistoryCategory(?string $category): ?string
    {
        $key = strtolower(trim((string) $category));
        if ($key === '' || $key === 'all') {
            return null;
        }

        if ($key === 'auth') {
            $key = 'authentication';
        }

        return array_key_exists($key, self::historyCategoryOptions()) ? $key : null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\WalletTransaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\WalletTransaction>
     */
    private function applyHistoryCategoryFilter($query, string $categoryKey)
    {
        $aliases = match ($categoryKey) {
            'marketing' => ['MARKETING', 'CAROUSEL'],
            'utility' => ['UTILITY'],
            'authentication' => ['AUTHENTICATION', 'AUTH'],
            'service' => ['SERVICE'],
            default => [],
        };

        if ($aliases === []) {
            return $query;
        }

        return $query->where(function ($nested) use ($aliases, $categoryKey): void {
            foreach ($aliases as $alias) {
                $nested->orWhere('metadata->pricing_category', $alias)
                    ->orWhere('metadata->template_category', $alias)
                    ->orWhere('metadata->legacy_category', $alias)
                    ->orWhere('metadata->pricing_category', strtolower($alias))
                    ->orWhere('metadata->template_category', strtolower($alias));
            }

            if ($categoryKey === 'service') {
                $nested->orWhere('description', 'like', '%service conversation%');
            }
        });
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
     * Campaigns that have wallet debits, with totals (for campaign-wise wallet history).
     *
     * @return LengthAwarePaginator<int, object{
     *     campaign: ?Campaign,
     *     campaign_id: int,
     *     charge_count: int,
     *     total_amount: float,
     *     last_charged_at: ?\Illuminate\Support\Carbon
     * }>
     */
    public function paginateCampaignWalletSummaries(int $perPage = 25): LengthAwarePaginator
    {
        // Aggregate in SQL. Loading every campaign debit (one row per message)
        // into PHP exhausts memory on large accounts and returns HTTP 500.
        $connection = WalletTransaction::query()->getConnection();
        $quotedType = $connection->getPdo()->quote(Campaign::class);
        $campaignIdSql = $connection->getDriverName() === 'sqlite'
            ? "CAST(COALESCE(
                NULLIF(NULLIF(json_extract(metadata, '$.campaign_id'), ''), '0'),
                NULLIF(NULLIF(json_extract(metadata, '$.legacy_campaign_id'), ''), '0'),
                CASE WHEN reference_type = {$quotedType} THEN reference_id END
              ) AS INTEGER)"
            : "CAST(COALESCE(
                NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.campaign_id')), ''), '0'),
                NULLIF(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.legacy_campaign_id')), ''), '0'),
                CASE WHEN reference_type = {$quotedType} THEN reference_id END
              ) AS UNSIGNED)";

        $base = WalletTransaction::query()
            ->where('type', WalletTransactionType::Debit)
            ->where(function ($query): void {
                $query->where('reference_type', Campaign::class)
                    ->orWhere('metadata->wallet_source', 'campaign')
                    ->orWhere(function ($nested): void {
                        $nested->whereNotNull('metadata->campaign_id')
                            ->where('metadata->campaign_id', '!=', '')
                            ->where('metadata->campaign_id', '!=', '0')
                            ->where('metadata->campaign_id', '!=', 0);
                    })
                    ->orWhere(function ($nested): void {
                        $nested->whereNotNull('metadata->legacy_campaign_id')
                            ->where('metadata->legacy_campaign_id', '!=', '')
                            ->where('metadata->legacy_campaign_id', '!=', '0')
                            ->where('metadata->legacy_campaign_id', '!=', 0);
                    });
            })
            ->selectRaw("{$campaignIdSql} as campaign_id")
            ->selectRaw('ABS(amount) as abs_amount')
            ->selectRaw('created_at')
            ->toBase();

        $rows = $connection->query()
            ->fromSub($base, 'charges')
            ->where('campaign_id', '>', 0)
            ->groupBy('campaign_id')
            ->selectRaw('campaign_id, COUNT(*) as charge_count, SUM(abs_amount) as total_amount, MAX(created_at) as last_charged_at')
            ->orderByDesc('last_charged_at')
            ->get();

        $campaignIds = $rows
            ->map(fn (object $row): int => (int) $row->campaign_id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $campaigns = $campaignIds === []
            ? collect()
            : Campaign::query()
                ->withTrashed()
                ->whereIn('id', $campaignIds)
                ->with(['template:id,name,category', 'audience:id,name'])
                ->get()
                ->keyBy('id');

        $summaries = $rows
            ->map(function (object $row) use ($campaigns): object {
                $chargedAt = $row->last_charged_at
                    ? \Illuminate\Support\Carbon::parse($row->last_charged_at)
                    : null;

                return (object) [
                    'campaign_id' => (int) $row->campaign_id,
                    'campaign' => $campaigns->get((int) $row->campaign_id),
                    'charge_count' => (int) $row->charge_count,
                    'total_amount' => round((float) $row->total_amount, 2),
                    'last_charged_at' => $chargedAt,
                ];
            })
            ->values();

        $page = max(1, (int) request()->integer('page', 1));
        $perPage = max(1, $perPage);
        $slice = $summaries->forPage($page, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $summaries->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * Wallet debits for one campaign (recipient / message level).
     */
    public function paginateCampaignCharges(Campaign $campaign, int $perPage = 50): LengthAwarePaginator
    {
        $paginator = $this->campaignChargesQuery($campaign)
            ->paginate($perPage)
            ->withQueryString();

        $recipientIds = $paginator->getCollection()
            ->map(function (WalletTransaction $txn): int {
                $meta = is_array($txn->metadata) ? $txn->metadata : [];

                return (int) ($meta['campaign_recipient_id'] ?? 0);
            })
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $recipients = $recipientIds === []
            ? collect()
            : CampaignRecipient::query()
                ->with('contact:id,name,phone')
                ->whereIn('id', $recipientIds)
                ->get()
                ->keyBy('id');

        $paginator->setCollection(
            $paginator->getCollection()->map(function (WalletTransaction $txn) use ($recipients): WalletTransaction {
                $meta = is_array($txn->metadata) ? $txn->metadata : [];
                $recipientId = (int) ($meta['campaign_recipient_id'] ?? 0);
                $txn->setAttribute('campaign_recipient', $recipientId > 0 ? $recipients->get($recipientId) : null);

                return $txn;
            }),
        );

        return $paginator;
    }

    public function exportCampaignChargesCsv(Campaign $campaign): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $safeName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) $campaign->name) ?: 'campaign';
        $filename = 'wallet-campaign-'.$safeName.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($campaign): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SI. No',
                'Phone',
                'Name',
                'Recipient Status',
                'Amount (INR)',
                'Category',
                'Description',
                'Msg ID',
                'Message ID',
                'Recipient ID',
                'Date',
            ]);

            $seq = 0;
            $this->campaignChargesQuery($campaign)
                ->cursor()
                ->each(function (WalletTransaction $transaction) use ($handle, &$seq): void {
                    $meta = is_array($transaction->metadata) ? $transaction->metadata : [];
                    $recipientId = (int) ($meta['campaign_recipient_id'] ?? 0);
                    $recipient = $recipientId > 0
                        ? CampaignRecipient::query()->with('contact:id,name,phone')->find($recipientId)
                        : null;

                    $phone = (string) (
                        $meta['contact_phone']
                        ?? $recipient?->contact_phone
                        ?? $recipient?->contact?->phone
                        ?? ''
                    );
                    $name = (string) ($recipient?->contact?->name ?? '');

                    fputcsv($handle, [
                        ++$seq,
                        $phone !== '' ? $phone : 'N/A',
                        $name !== '' ? $name : 'N/A',
                        $recipient?->status?->label() ?? 'N/A',
                        number_format(abs((float) $transaction->amount), 2, '.', ''),
                        (string) ($meta['pricing_category'] ?? $meta['template_category'] ?? $meta['legacy_category'] ?? 'N/A'),
                        (string) ($transaction->description ?: 'N/A'),
                        (string) ($meta['external_message_id'] ?? $meta['legacy_msg_id'] ?? 'N/A'),
                        (string) ($meta['message_id'] ?? 'N/A'),
                        $recipientId > 0 ? (string) $recipientId : 'N/A',
                        $transaction->created_at?->format('d M Y h:i:s A') ?? 'N/A',
                    ]);
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function campaignChargesTotal(Campaign $campaign): float
    {
        return round(abs((float) $this->campaignChargesQuery($campaign)->sum('amount')), 2);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\WalletTransaction>
     */
    private function campaignChargesQuery(Campaign $campaign)
    {
        $id = (int) $campaign->id;

        return WalletTransaction::query()
            ->where('type', WalletTransactionType::Debit)
            ->where(function ($query) use ($id, $campaign): void {
                $query->where(function ($nested) use ($campaign): void {
                    $nested->where('reference_type', Campaign::class)
                        ->where('reference_id', $campaign->id);
                })
                    ->orWhere('metadata->campaign_id', $id)
                    ->orWhere('metadata->campaign_id', (string) $id)
                    ->orWhere('metadata->legacy_campaign_id', $id)
                    ->orWhere('metadata->legacy_campaign_id', (string) $id);
            })
            ->latest('id');
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
     *
     * @param  array{admin_id?: int|null, admin_name?: string|null, admin_email?: string|null}  $actor
     */
    public function adminCredit(
        float $amount,
        string $description = 'Admin wallet credit',
        array $actor = [],
    ): WalletTransaction {
        abort_unless($amount > 0, 422, 'Credit amount must be greater than zero.');

        $amount = round($amount, 2);

        $transaction = DB::transaction(function () use ($amount, $description, $actor): WalletTransaction {
            $wallet = WalletAccount::query()->lockForUpdate()->first();
            if ($wallet === null) {
                $wallet = $this->account();
                $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $previousBalance = round((float) $wallet->balance, 2);
            $newBalance = round($previousBalance + $amount, 2);
            $wallet->update(['balance' => $newBalance]);

            return WalletTransaction::query()->create([
                'type' => WalletTransactionType::Credit,
                'amount' => $amount,
                'currency' => $wallet->currency ?? 'INR',
                'balance_after' => $newBalance,
                'description' => $description,
                'metadata' => array_filter([
                    'source' => 'admin_credit',
                    'previous_balance' => $previousBalance,
                    'admin_id' => $actor['admin_id'] ?? null,
                    'admin_name' => $actor['admin_name'] ?? null,
                    'admin_email' => $actor['admin_email'] ?? null,
                ], static fn ($v) => $v !== null && $v !== ''),
                'created_at' => now(),
            ]);
        });

        try {
            app(\App\Domains\Account\Services\ActivityLogService::class)->log('billing.wallet.admin_credit', [
                'description' => sprintf(
                    'Wallet - admin credited ₹%s (balance ₹%s → ₹%s)',
                    number_format($amount, 2),
                    number_format((float) data_get($transaction->metadata, 'previous_balance', 0), 2),
                    number_format((float) $transaction->balance_after, 2),
                ),
                'actor_name' => $actor['admin_name'] ?? null,
                'actor_email' => $actor['admin_email'] ?? null,
                'metadata' => [
                    'amount' => $amount,
                    'previous_balance' => data_get($transaction->metadata, 'previous_balance'),
                    'balance_after' => (float) $transaction->balance_after,
                    'admin_id' => $actor['admin_id'] ?? null,
                    'wallet_transaction_id' => $transaction->id,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin wallet credit activity log failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $transaction;
    }

    /**
     * Debit the current tenant wallet from admin (no payment gateway).
     *
     * @param  array{admin_id?: int|null, admin_name?: string|null, admin_email?: string|null}  $actor
     */
    public function adminDebit(
        float $amount,
        string $description = 'Admin wallet debit',
        array $actor = [],
        bool $allowNegative = false,
    ): WalletTransaction {
        abort_unless($amount > 0, 422, 'Debit amount must be greater than zero.');

        $amount = round($amount, 2);

        $transaction = $this->debit(
            amount: $amount,
            description: $description,
            metadata: array_filter([
                'source' => 'admin_debit',
                'admin_id' => $actor['admin_id'] ?? null,
                'admin_name' => $actor['admin_name'] ?? null,
                'admin_email' => $actor['admin_email'] ?? null,
            ], static fn ($v) => $v !== null && $v !== ''),
            allowNegative: $allowNegative,
        );

        try {
            app(\App\Domains\Account\Services\ActivityLogService::class)->log('billing.wallet.admin_debit', [
                'description' => sprintf(
                    'Wallet - admin deducted ₹%s (balance after ₹%s)',
                    number_format($amount, 2),
                    number_format((float) $transaction->balance_after, 2),
                ),
                'actor_name' => $actor['admin_name'] ?? null,
                'actor_email' => $actor['admin_email'] ?? null,
                'metadata' => [
                    'amount' => $amount,
                    'balance_after' => (float) $transaction->balance_after,
                    'admin_id' => $actor['admin_id'] ?? null,
                    'wallet_transaction_id' => $transaction->id,
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin wallet debit activity log failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $transaction;
    }

    /**
     * Delete a wallet ledger row and reverse its effect on the current balance.
     */
    public function deleteTransaction(WalletTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $locked = WalletTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $wallet = WalletAccount::query()->lockForUpdate()->first();
            if ($wallet === null) {
                $wallet = $this->account();
                $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $amount = round((float) $locked->amount, 2);
            $current = round((float) $wallet->balance, 2);
            $type = $locked->type instanceof WalletTransactionType
                ? $locked->type
                : WalletTransactionType::tryFrom((string) $locked->type);

            if ($type === WalletTransactionType::Credit) {
                $wallet->update(['balance' => round($current - $amount, 2)]);
            } elseif ($type === WalletTransactionType::Debit) {
                $wallet->update(['balance' => round($current + $amount, 2)]);
            }

            $locked->delete();
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
