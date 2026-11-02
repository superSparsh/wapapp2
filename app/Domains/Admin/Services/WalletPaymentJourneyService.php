<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Models\RazorpayOrder;
use App\Models\Tenant;
use App\Models\WalletTransaction;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use RuntimeException;

class WalletPaymentJourneyService
{
    public function __construct(
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * @param  array{q?: string, tenant?: string, status?: string, sort?: string, direction?: string}  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function listJourneys(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $filters = $this->normalize($filters);

        $rows = $this->scanner->map(function () use ($filters): array {
            $query = RazorpayOrder::query()
                ->where('purpose', RazorpayOrderPurpose::WalletRecharge)
                ->latest('id')
                ->limit(150);

            if ($filters['q'] !== '') {
                $q = $filters['q'];
                $query->where(function ($builder) use ($q): void {
                    $builder->where('razorpay_order_id', 'like', "%{$q}%")
                        ->orWhere('razorpay_payment_id', 'like', "%{$q}%")
                        ->orWhere('uuid', 'like', "%{$q}%");
                });
            }

            $orders = $query->get();
            if ($orders->isEmpty()) {
                return [];
            }

            $orderIds = $orders->pluck('id')->all();
            $paymentIds = $orders->pluck('razorpay_payment_id')->filter()->values()->all();

            $walletByOrderId = WalletTransaction::query()
                ->where('reference_type', RazorpayOrder::class)
                ->whereIn('reference_id', $orderIds)
                ->get()
                ->keyBy(fn (WalletTransaction $tx): int => (int) $tx->reference_id);

            $zohoByPayment = collect();
            if ($paymentIds !== []) {
                $zohoByPayment = tenancy()->central(function () use ($paymentIds) {
                    return ZohoWalletCreditRequest::query()
                        ->where('source', 'razorpay')
                        ->whereIn('external_id', $paymentIds)
                        ->get()
                        ->keyBy(fn (ZohoWalletCreditRequest $row): string => (string) $row->external_id);
                });
            }

            return $orders->map(function (RazorpayOrder $order) use ($walletByOrderId, $zohoByPayment): array {
                $wallet = $walletByOrderId->get((int) $order->id);
                $zoho = $order->razorpay_payment_id
                    ? $zohoByPayment->get((string) $order->razorpay_payment_id)
                    : null;

                return $this->toJourneyRow($order, $wallet, $zoho);
            })->all();
        }, $filters['tenant'] ?: null);

        if ($filters['status'] !== '') {
            $rows = $rows->filter(
                fn (array $row): bool => ($row['journey_status'] ?? '') === $filters['status']
            )->values();
        }

        $sorted = AdminListQuery::sortRows(
            $rows,
            $filters['sort'],
            $filters['direction'],
            ['created_at', 'amount', 'id'],
            'created_at',
        );

        return $this->paginate($sorted, $filters, $page, $perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(string $tenantId, string $orderUuid): array
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            throw new RuntimeException('Customer not found.');
        }

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;
        if ($wasInitialized) {
            tenancy()->end();
        }

        try {
            tenancy()->initialize($tenant);

            /** @var RazorpayOrder|null $order */
            $order = RazorpayOrder::query()
                ->where('uuid', $orderUuid)
                ->where('purpose', RazorpayOrderPurpose::WalletRecharge)
                ->first();

            if ($order === null) {
                throw new RuntimeException('Wallet payment order not found.');
            }

            $wallet = WalletTransaction::query()
                ->where('reference_type', RazorpayOrder::class)
                ->where('reference_id', $order->id)
                ->first();

            $zoho = null;
            if ($order->razorpay_payment_id) {
                $paymentId = (string) $order->razorpay_payment_id;
                $zoho = tenancy()->central(function () use ($paymentId, $tenantId) {
                    return ZohoWalletCreditRequest::query()
                        ->where('source', 'razorpay')
                        ->where('external_id', $paymentId)
                        ->where('tenant_id', $tenantId)
                        ->first();
                });
            }

            $row = $this->toJourneyRow($order, $wallet, $zoho);
            $row['tenant_id'] = (string) $tenant->id;
            $row['tenant_name'] = (string) ($tenant->company_name ?: $tenant->name);
            $row['steps'] = $this->buildSteps($order, $wallet, $zoho);
            $row['can_complete_from_razorpay'] = $order->status !== RazorpayOrderStatus::Paid || $wallet === null;
            $row['can_retry_zoho'] = $order->status === RazorpayOrderStatus::Paid
                && $wallet !== null
                && ($zoho === null || in_array((string) $zoho->status, ['pending', 'failed'], true));
            $row['order'] = [
                'uuid' => (string) $order->uuid,
                'id' => (int) $order->id,
                'razorpay_order_id' => (string) $order->razorpay_order_id,
                'razorpay_payment_id' => (string) ($order->razorpay_payment_id ?? ''),
                'status' => $order->status?->value,
                'amount' => (string) $order->amount,
                'tax_amount' => (string) ($order->tax_amount ?? '0'),
                'total_amount' => (string) $order->total_amount,
                'currency' => (string) $order->currency,
                'created_at' => optional($order->created_at)?->toDateTimeString(),
                'paid_at' => optional($order->paid_at)?->toDateTimeString(),
                'metadata' => $order->metadata ?? [],
            ];
            $row['wallet'] = $wallet === null ? null : [
                'id' => (int) $wallet->id,
                'amount' => (string) $wallet->amount,
                'description' => (string) ($wallet->description ?? ''),
                'razorpay_payment_id' => (string) ($wallet->razorpay_payment_id ?? ''),
                'created_at' => optional($wallet->created_at)?->toDateTimeString(),
            ];
            $row['zoho'] = $zoho === null ? null : [
                'id' => (int) $zoho->id,
                'uuid' => (string) $zoho->uuid,
                'status' => (string) $zoho->status,
                'invoice_number' => (string) ($zoho->invoice_number ?? ''),
                'last_error' => (string) ($zoho->last_error ?? ''),
                'wallet_credited_at' => optional($zoho->wallet_credited_at)?->toDateTimeString(),
                'created_at' => optional($zoho->created_at)?->toDateTimeString(),
            ];

            return $row;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($previous !== null) {
                tenancy()->initialize($previous);
            }
        }
    }

    /**
     * @return list<array{key: string, label: string, state: string, detail: string}>
     */
    private function buildSteps(
        RazorpayOrder $order,
        ?WalletTransaction $wallet,
        ?ZohoWalletCreditRequest $zoho,
    ): array {
        $orderDone = true;
        $paidDone = $order->status === RazorpayOrderStatus::Paid || filled($order->razorpay_payment_id);
        $walletDone = $wallet !== null;
        $zohoState = 'pending';
        $zohoDetail = 'Waiting for wallet credit';

        if ($zoho !== null) {
            $status = (string) $zoho->status;
            if ($status === 'synced') {
                $zohoState = 'done';
                $zohoDetail = filled($zoho->invoice_number)
                    ? 'Invoice '.$zoho->invoice_number
                    : 'Synced';
            } elseif ($status === 'failed') {
                $zohoState = 'error';
                $zohoDetail = (string) ($zoho->last_error ?: 'Zoho sync failed');
            } else {
                $zohoState = 'active';
                $zohoDetail = 'Pending Zoho invoice';
            }
        } elseif ($walletDone) {
            $zohoState = 'active';
            $zohoDetail = 'Not started yet — retry if needed';
        }

        return [
            [
                'key' => 'order',
                'label' => 'Order created',
                'state' => $orderDone ? 'done' : 'pending',
                'detail' => (string) $order->razorpay_order_id,
            ],
            [
                'key' => 'paid',
                'label' => 'Paid on Razorpay',
                'state' => $paidDone ? 'done' : ($order->status === RazorpayOrderStatus::Failed ? 'error' : 'active'),
                'detail' => $paidDone
                    ? (string) ($order->razorpay_payment_id ?: 'Marked paid')
                    : ($order->status === RazorpayOrderStatus::Failed ? 'Payment failed' : 'Awaiting capture / verify'),
            ],
            [
                'key' => 'wallet',
                'label' => 'Wallet credited',
                'state' => $walletDone ? 'done' : ($paidDone ? 'error' : 'pending'),
                'detail' => $walletDone
                    ? ((string) $wallet->currency).' '.$wallet->amount
                    : ($paidDone ? 'Missing — use Complete from Razorpay' : 'Waiting for payment'),
            ],
            [
                'key' => 'zoho',
                'label' => 'Zoho invoice',
                'state' => $zohoState,
                'detail' => $zohoDetail,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toJourneyRow(
        RazorpayOrder $order,
        ?WalletTransaction $wallet,
        ?ZohoWalletCreditRequest $zoho,
    ): array {
        $journeyStatus = $this->resolveJourneyStatus($order, $wallet, $zoho);

        return [
            'order_uuid' => (string) $order->uuid,
            'order_id' => (int) $order->id,
            'razorpay_order_id' => (string) $order->razorpay_order_id,
            'razorpay_payment_id' => (string) ($order->razorpay_payment_id ?? ''),
            'order_status' => $order->status?->value,
            'amount' => (string) $order->amount,
            'total_amount' => (string) $order->total_amount,
            'currency' => (string) $order->currency,
            'created_at' => optional($order->created_at)?->toDateTimeString(),
            'paid_at' => optional($order->paid_at)?->toDateTimeString(),
            'wallet_credited' => $wallet !== null,
            'wallet_amount' => $wallet !== null ? (string) $wallet->amount : null,
            'zoho_status' => $zoho?->status,
            'zoho_invoice' => $zoho?->invoice_number,
            'journey_status' => $journeyStatus,
            'journey_label' => $this->journeyLabel($journeyStatus),
        ];
    }

    private function resolveJourneyStatus(
        RazorpayOrder $order,
        ?WalletTransaction $wallet,
        ?ZohoWalletCreditRequest $zoho,
    ): string {
        if ($order->status === RazorpayOrderStatus::Failed) {
            return 'failed';
        }

        if ($order->status !== RazorpayOrderStatus::Paid) {
            return 'awaiting_payment';
        }

        if ($wallet === null) {
            return 'paid_wallet_missing';
        }

        $zohoStatus = $zoho !== null ? (string) $zoho->status : '';
        if ($zohoStatus === 'synced') {
            return 'complete';
        }
        if ($zohoStatus === 'failed') {
            return 'zoho_failed';
        }

        return 'zoho_pending';
    }

    private function journeyLabel(string $status): string
    {
        return match ($status) {
            'complete' => 'Complete',
            'awaiting_payment' => 'Awaiting payment',
            'paid_wallet_missing' => 'Paid · wallet missing',
            'zoho_pending' => 'Wallet OK · Zoho pending',
            'zoho_failed' => 'Wallet OK · Zoho failed',
            'failed' => 'Failed',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * @param  array{q?: string, tenant?: string, status?: string, sort?: string, direction?: string}  $filters
     * @return array{q: string, tenant: string, status: string, sort: string, direction: string}
     */
    private function normalize(array $filters): array
    {
        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (! in_array($sort, ['created_at', 'amount', 'id'], true)) {
            $sort = 'created_at';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'tenant' => trim((string) ($filters['tenant'] ?? $filters['tenant_id'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{q: string, tenant: string, status: string, sort: string, direction: string}  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    private function paginate(Collection $rows, array $filters, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter($filters)],
        );

        return ['items' => $paginator, 'filters' => $filters];
    }
}
