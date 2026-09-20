<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Models\RazorpayOrder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function isEnabled(): bool
    {
        return $this->platformSettings->get('payment.razorpay_enabled', '0') === '1';
    }

    public function key(): string
    {
        return trim((string) $this->platformSettings->get('payment.razorpay_key', ''));
    }

    public function secret(): string
    {
        return trim((string) $this->platformSettings->get('payment.razorpay_secret', ''));
    }

    public function webhookSecret(): string
    {
        return trim((string) $this->platformSettings->get('payment.razorpay_webhook_secret', ''));
    }

    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && $this->key() !== ''
            && $this->secret() !== '';
    }

    /**
     * @param  array<string, mixed>  $notes
     */
    public function createOrder(
        float $amount,
        string $currency,
        RazorpayOrderPurpose $purpose,
        array $notes = [],
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Razorpay is not configured. Set credentials under Admin → Payment gateways.',
            );
        }

        $amountPaise = (int) round($amount * 100);

        $response = Http::withBasicAuth(
            $this->key(),
            $this->secret(),
        )->post('https://api.razorpay.com/v1/orders', [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => 'wapapp_'.uniqid(),
            'notes' => array_merge($notes, [
                'purpose' => $purpose->value,
                'tenant_id' => tenant('id'),
            ]),
        ])->throw();

        return $response->json();
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = $this->secret();
        if ($secret === '') {
            return false;
        }

        $payload = $orderId.'|'.$paymentId;
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Validate Razorpay payment against the local GST-inclusive order (legacy wallet credit parity).
     *
     * - Payment must belong to the order
     * - Payment status must be captured/authorized
     * - Razorpay order amount must match local total_amount (paise)
     * - Paid amount may exceed order amount (gateway fees); underpayment is rejected
     *
     * @return array{payment: array<string, mixed>, order: array<string, mixed>}
     */
    public function assertPaymentMatchesOrder(RazorpayOrder $localOrder, string $paymentId): array
    {
        $payment = $this->fetchPayment($paymentId);
        $status = (string) ($payment['status'] ?? '');

        if (! in_array($status, ['captured', 'authorized'], true)) {
            throw new RuntimeException('Payment is not completed yet.');
        }

        $paymentOrderId = (string) ($payment['order_id'] ?? '');
        if ($paymentOrderId === '' || $paymentOrderId !== (string) $localOrder->razorpay_order_id) {
            throw new RuntimeException('Payment does not belong to this order.');
        }

        $remoteOrder = $this->fetchOrder((string) $localOrder->razorpay_order_id);
        $orderPaise = (int) ($remoteOrder['amount'] ?? 0);
        $paidPaise = (int) ($payment['amount'] ?? 0);
        $expectedPaise = (int) round((float) $localOrder->total_amount * 100);

        if ($orderPaise !== $expectedPaise) {
            throw new RuntimeException('Paid amount does not match the GST-inclusive order.');
        }

        // Never accept underpayment; gateway fees above the order amount are ignored.
        if ($paidPaise < $orderPaise) {
            throw new RuntimeException('Paid amount does not match the GST-inclusive order.');
        }

        return [
            'payment' => $payment,
            'order' => $remoteOrder,
        ];
    }

    public function markOrderPaid(RazorpayOrder $order, string $paymentId): RazorpayOrder
    {
        $order->update([
            'razorpay_payment_id' => $paymentId,
            'status' => RazorpayOrderStatus::Paid,
            'paid_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * @throws RequestException
     * @return array<string, mixed>
     */
    public function fetchPayment(string $paymentId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Razorpay is not configured. Set credentials under Admin → Payment gateways.',
            );
        }

        $response = Http::withBasicAuth(
            $this->key(),
            $this->secret(),
        )->get('https://api.razorpay.com/v1/payments/'.$paymentId)->throw();

        return $response->json();
    }

    /**
     * @throws RequestException
     * @return array<string, mixed>
     */
    public function fetchOrder(string $orderId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Razorpay is not configured. Set credentials under Admin → Payment gateways.',
            );
        }

        $response = Http::withBasicAuth(
            $this->key(),
            $this->secret(),
        )->get('https://api.razorpay.com/v1/orders/'.$orderId)->throw();

        return $response->json();
    }
}
