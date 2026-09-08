<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Services;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Enums\PaymentStatus;
use App\Domains\Commerce\Models\CommerceOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * CRUD + enrichment service for WhatsApp commerce orders.
 */
class CommerceOrderService
{
    /**
     * Paginate orders with optional search and status filters.
     */
    public function paginate(
        ?string $search = null,
        ?string $orderStatus = null,
        ?string $paymentStatus = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $search        = trim((string) $search);
        $orderStatus   = filled($orderStatus) ? $orderStatus : null;
        $paymentStatus = filled($paymentStatus) ? $paymentStatus : null;

        return CommerceOrder::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_phone', 'like', "%{$search}%")
                        ->orWhere('catalog_id', 'like', "%{$search}%");
                });
            })
            ->when($orderStatus !== null, fn ($q) => $q->where('order_status', $orderStatus))
            ->when($paymentStatus !== null, fn ($q) => $q->where('payment_status', $paymentStatus))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Find a single order by UUID.
     */
    public function findByUuid(string $uuid): CommerceOrder
    {
        return CommerceOrder::query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Create a new order (called by incoming WhatsApp commerce webhook).
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): CommerceOrder
    {
        return CommerceOrder::query()->create($data);
    }

    /**
     * Update order status (e.g. mark as confirmed, shipped, etc.).
     */
    public function updateOrderStatus(CommerceOrder $order, OrderStatus $status): CommerceOrder
    {
        $order->update(['order_status' => $status]);

        return $order->fresh();
    }

    /**
     * Update payment status on an order.
     */
    public function updatePaymentStatus(CommerceOrder $order, PaymentStatus $status, ?string $paymentLink = null): CommerceOrder
    {
        $updates = ['payment_status' => $status];

        if ($paymentLink !== null) {
            $updates['payment_link'] = $paymentLink;
        }

        $order->update($updates);

        return $order->fresh();
    }

    /**
     * Enrich order product_items array with live product data from the catalog.
     * If a product's retailer_id matches, we merge in name + image_url.
     *
     * @param  list<array<string, mixed>>  $products  Formatted products from CatalogService
     */
    public function enrichOrderItems(CommerceOrder $order, array $products): CommerceOrder
    {
        $items = $order->product_items ?? [];

        if (empty($items) || empty($products)) {
            return $order;
        }

        $productMap = collect($products)->keyBy('retailer_id');

        $enriched = array_map(function (array $item) use ($productMap): array {
            $retailerId = (string) ($item['product_retailer_id'] ?? '');
            $product    = $productMap->get($retailerId);

            if ($product) {
                $item['name']      = $product['name'];
                $item['image_url'] = $product['image_url'];
                $item['price']     = $product['price'];
            }

            return $item;
        }, $items);

        $order->product_items = $enriched;

        return $order;
    }

    /**
     * Collect quick stats for the orders dashboard.
     *
     * @return array{total: int, new: int, confirmed: int, delivered: int, cancelled: int, paid: int, pending: int}
     */
    public function getStats(): array
    {
        $counts = CommerceOrder::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as cnt_new,
                SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as cnt_confirmed,
                SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as cnt_delivered,
                SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as cnt_cancelled,
                SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as cnt_paid,
                SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as cnt_pending
            ', [
                OrderStatus::New->value,
                OrderStatus::Confirmed->value,
                OrderStatus::Delivered->value,
                OrderStatus::Cancelled->value,
                PaymentStatus::Paid->value,
                PaymentStatus::Pending->value,
            ])
            ->first();

        return [
            'total'     => (int) ($counts?->total ?? 0),
            'new'       => (int) ($counts?->cnt_new ?? 0),
            'confirmed' => (int) ($counts?->cnt_confirmed ?? 0),
            'delivered' => (int) ($counts?->cnt_delivered ?? 0),
            'cancelled' => (int) ($counts?->cnt_cancelled ?? 0),
            'paid'      => (int) ($counts?->cnt_paid ?? 0),
            'pending'   => (int) ($counts?->cnt_pending ?? 0),
        ];
    }

    /**
     * Get recent orders (for dashboard widget).
     *
     * @return Collection<int, CommerceOrder>
     */
    public function recent(int $limit = 10): Collection
    {
        return CommerceOrder::query()->latest('id')->limit($limit)->get();
    }
}
