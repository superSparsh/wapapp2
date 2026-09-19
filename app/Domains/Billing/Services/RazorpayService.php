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
}
