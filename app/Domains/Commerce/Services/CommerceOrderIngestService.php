<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Services;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Enums\PaymentStatus;
use App\Domains\Commerce\Jobs\SendPaymentLinkJob;
use App\Domains\Commerce\Models\CommerceOrder;
use App\Domains\Commerce\Models\CommercePayment;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingest WhatsApp ORDER payloads into commerce_orders and create payment links.
 */
class CommerceOrderIngestService
{
    public function __construct(
        private readonly CommerceOrderService $orders,
        private readonly CommercePaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  CAMS/Alibaba inbound item
     */
    public function ingest(array $payload, WhatsappLine $line): ?CommerceOrder
    {
        $rawMessage = $payload['Message'] ?? null;
        $messageData = is_string($rawMessage) ? json_decode($rawMessage, true) : (is_array($rawMessage) ? $rawMessage : null);

        if (! is_array($messageData) || empty($messageData['product_items']) || ! is_array($messageData['product_items'])) {
            Log::info('Commerce: invalid ORDER payload', ['message_id' => $payload['MessageId'] ?? null]);

            return null;
        }

        $items = $messageData['product_items'];
        $total = collect($items)->sum(function ($item): float {
            return ((float) ($item['item_price'] ?? 0)) * ((float) ($item['quantity'] ?? 1));
        });

        $currency = strtoupper((string) ($items[0]['currency'] ?? 'INR'));
        $externalMessageId = (string) ($payload['MessageId'] ?? '');

        if ($externalMessageId !== '' && CommerceOrder::query()->where('external_message_id', $externalMessageId)->exists()) {
            return CommerceOrder::query()->where('external_message_id', $externalMessageId)->first();
        }

        $order = $this->orders->create([
            'catalog_id' => $messageData['catalog_id'] ?? null,
            'customer_name' => (string) ($payload['Name'] ?? 'Unknown'),
            'customer_phone' => (string) ($payload['From'] ?? ''),
            'product_items' => $items,
            'total_price' => $total,
            'currency' => $currency,
            'order_status' => OrderStatus::New,
            'payment_status' => PaymentStatus::Pending,
            'whatsapp_line_id' => $line->id,
            'external_message_id' => $externalMessageId !== '' ? $externalMessageId : null,
            'metadata' => ['waba_id' => $payload['WabaId'] ?? null, 'raw' => $messageData],
        ]);

        try {
            $payment = $this->payments->createPaymentLink([
                'customer_name' => (string) $order->customer_name,
                'customer_phone' => (string) $order->customer_phone,
                'amount' => (float) $order->total_price,
                'currency' => (string) $order->currency,
                'commerce_order_id' => $order->id,
            ]);

            $this->orders->updatePaymentStatus($order, PaymentStatus::Pending, $payment->payment_link);
            SendPaymentLinkJob::dispatch($payment, (string) tenant('id'));
        } catch (Throwable $e) {
            Log::warning('Commerce: payment link not created for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $order->fresh();
    }
}
