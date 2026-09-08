<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Models\RazorpayOrder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayService
{
    public function isConfigured(): bool
    {
        return filled(config('billing.razorpay.key'))
            && filled(config('billing.razorpay.secret'));
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
            throw new RuntimeException('Razorpay is not configured. Set RAZORPAY_KEY and RAZORPAY_SECRET.');
        }

        $amountPaise = (int) round($amount * 100);

        $response = Http::withBasicAuth(
            (string) config('billing.razorpay.key'),
            (string) config('billing.razorpay.secret'),
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
        $secret = (string) config('billing.razorpay.secret');
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
        $response = Http::withBasicAuth(
            (string) config('billing.razorpay.key'),
            (string) config('billing.razorpay.secret'),
        )->get('https://api.razorpay.com/v1/payments/'.$paymentId)->throw();

        return $response->json();
    }
}
