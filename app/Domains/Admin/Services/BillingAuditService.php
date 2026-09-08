<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\WalletTransactionType;
use App\Models\RazorpayOrder;
use App\Models\Subscription;
use App\Models\WalletTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingAuditService
{
    public function __construct(
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * @param  array{q?: string, tenant_id?: string, type?: string}  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function walletRecharges(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $filters = $this->normalize($filters);
        $rows = $this->scanner->map(function () use ($filters): array {
            $query = WalletTransaction::query()
                ->where('type', WalletTransactionType::Credit)
                ->latest('id')
                ->limit(100);

            if ($filters['q'] !== '') {
                $q = $filters['q'];
                $query->where(function ($builder) use ($q): void {
                    $builder->where('description', 'like', "%{$q}%")
                        ->orWhere('razorpay_payment_id', 'like', "%{$q}%")
                        ->orWhere('reference_id', 'like', "%{$q}%");
                });
            }

            return $query->get()->map(fn (WalletTransaction $tx): array => [
                'id' => $tx->id,
                'uuid' => $tx->uuid ?? null,
                'amount' => (string) $tx->amount,
                'currency' => (string) $tx->currency,
                'description' => (string) ($tx->description ?? ''),
                'razorpay_payment_id' => (string) ($tx->razorpay_payment_id ?? ''),
                'created_at' => optional($tx->created_at)?->toDateTimeString(),
                'source' => 'wallet_credit',
            ])->all();
        }, $filters['tenant'] ?: null);

        return $this->paginate($rows->sortByDesc('created_at')->values(), $filters, $page, $perPage);
    }

    /**
     * @param  array{q?: string, tenant_id?: string, type?: string}  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function audit(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $filters = $this->normalize($filters);
        $type = $filters['type'] !== '' ? $filters['type'] : 'all';

        $rows = collect();
        if (in_array($type, ['all', 'wallet'], true)) {
            $rows = $rows->merge($this->walletRecharges(['tenant' => $filters['tenant'], 'q' => $filters['q'], 'type' => ''], 1, 5000)['items']->items());
        }

        if (in_array($type, ['all', 'razorpay'], true)) {
            $rows = $rows->merge($this->scanner->map(function () use ($filters): array {
                $query = RazorpayOrder::query()->latest('id')->limit(100);
                if ($filters['q'] !== '') {
                    $q = $filters['q'];
                    $query->where(function ($builder) use ($q): void {
                        $builder->where('razorpay_order_id', 'like', "%{$q}%")
                            ->orWhere('razorpay_payment_id', 'like', "%{$q}%");
                    });
                }

                return $query->get()->map(fn (RazorpayOrder $order): array => [
                    'id' => $order->id,
                    'amount' => (string) $order->total_amount,
                    'currency' => (string) $order->currency,
                    'description' => 'Razorpay '.$order->purpose?->value.' / '.$order->status?->value,
                    'razorpay_payment_id' => (string) ($order->razorpay_payment_id ?? $order->razorpay_order_id),
                    'created_at' => optional($order->paid_at ?? $order->created_at)?->toDateTimeString(),
                    'source' => 'razorpay_order',
                ])->all();
            }, $filters['tenant'] ?: null));
        }

        if (in_array($type, ['all', 'subscriptions'], true)) {
            $rows = $rows->merge($this->scanner->map(function () use ($filters): array {
                $query = Subscription::query()->latest('id')->limit(100);
                if ($filters['q'] !== '') {
                    $q = $filters['q'];
                    $query->where('razorpay_subscription_id', 'like', "%{$q}%");
                }

                return $query->get()->map(fn (Subscription $sub): array => [
                    'id' => $sub->id,
                    'amount' => (string) $sub->amount,
                    'currency' => (string) $sub->currency,
                    'description' => 'Subscription '.$sub->status?->value,
                    'razorpay_payment_id' => (string) ($sub->razorpay_subscription_id ?? ''),
                    'created_at' => optional($sub->starts_at ?? $sub->created_at)?->toDateTimeString(),
                    'source' => 'subscription',
                ])->all();
            }, $filters['tenant'] ?: null));
        }

        return $this->paginate($rows->sortByDesc('created_at')->values(), $filters, $page, $perPage);
    }

    /**
     * @param  array{q?: string, tenant_id?: string, type?: string}  $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $report = $this->audit($filters, 1, 50_000);
        $rows = collect($report['items']->items());

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['tenant_id', 'tenant_name', 'source', 'amount', 'currency', 'description', 'reference', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['tenant_id'] ?? '',
                    $row['tenant_name'] ?? '',
                    $row['source'] ?? '',
                    $row['amount'] ?? '',
                    $row['currency'] ?? '',
                    $row['description'] ?? '',
                    $row['razorpay_payment_id'] ?? '',
                    $row['created_at'] ?? '',
                ]);
            }
            fclose($out);
        }, 'billing-audit-'.now()->format('Ymd-His').'.csv');
    }

    /**
     * @return array{q: string, tenant: string, type: string}
     */
    private function normalize(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'tenant' => trim((string) ($filters['tenant'] ?? $filters['tenant_id'] ?? '')),
            'type' => trim((string) ($filters['type'] ?? '')),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{q: string, tenant: string, type: string}  $filters
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
