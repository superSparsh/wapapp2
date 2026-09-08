<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Services;

use App\Domains\Commerce\Enums\PaymentLinkStatus;
use App\Domains\Commerce\Models\CommercePayment;
use App\Domains\Commerce\Models\PaymentConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Handles Razorpay Payment Link creation and status updates using
 * per-tenant credentials stored in commerce_payment_configs.
 */
class CommercePaymentService
{
    private const RAZORPAY_PAYMENT_LINKS_URL = 'https://api.razorpay.com/v1/payment_links';
    private const PAYMENT_LINK_EXPIRY_HOURS  = 24;

    /**
     * Save (upsert) the tenant's Razorpay payment configuration.
     *
     * @param array{client_name: string, razorpay_key: string, razorpay_secret: string, payment_template_id?: int|null, confirmation_template_id?: int|null} $data
     */
    public function saveConfig(array $data): PaymentConfig
    {
        // If secret is blank on update, remove it so the encrypted value is preserved
        if (blank($data['razorpay_secret'] ?? null)) {
            unset($data['razorpay_secret']);
        }

        $config = PaymentConfig::query()->first();

        if ($config instanceof PaymentConfig) {
            $config->update($data);

            return $config->fresh();
        }

        return PaymentConfig::query()->create($data);
    }

    /**
     * Get the active payment configuration (or null if not yet set).
     */
    public function getConfig(): ?PaymentConfig
    {
        return PaymentConfig::query()->first();
    }

    /**
     * Create a Razorpay payment link and persist a CommercePayment record.
     *
     * @param array{customer_name: string, customer_phone: string, amount: float|string, currency?: string} $data
     * @throws RuntimeException when config is missing or Razorpay API fails
     */
    public function createPaymentLink(array $data): CommercePayment
    {
        $config = $this->getConfig();

        if (! $config || ! $config->isConfigured()) {
            throw new RuntimeException('Razorpay credentials are not configured. Please set up Payment Configuration first.');
        }

        $amount   = (float) preg_replace('/[^0-9.]/', '', (string) $data['amount']);
        $currency = strtoupper((string) ($data['currency'] ?? 'INR'));
        $phone    = $this->normalizePhone((string) $data['customer_phone']);
        $ref      = $this->generateOrderRef();
        $expireBy = now()->addHours(self::PAYMENT_LINK_EXPIRY_HOURS)->timestamp;

        $payload = [
            'amount'                    => (int) round($amount * 100), // paisa
            'currency'                  => $currency,
            'accept_partial'            => false,
            'expire_by'                 => $expireBy,
            'reference_id'              => $ref,
            'description'               => "Payment for Order #{$ref}",
            'customer'                  => [
                'name'    => (string) $data['customer_name'],
                'contact' => $phone,
            ],
            'notify'                    => ['sms' => true, 'email' => false],
            'reminder_enable'           => true,
            'notes'                     => ['internal_ref' => $ref, 'tenant_id' => tenant('id')],
            'callback_url'              => route('commerce.payment.callback'),
            'callback_method'           => 'get',
        ];

        try {
            $response = Http::withBasicAuth($config->razorpay_key, $config->razorpay_secret)
                ->timeout(20)
                ->post(self::RAZORPAY_PAYMENT_LINKS_URL, $payload)
                ->throw();

            $body = $response->json();
        } catch (RequestException $e) {
            Log::error('Commerce: Razorpay payment link creation failed', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw new RuntimeException('Failed to create Razorpay payment link: '.$e->getMessage(), previous: $e);
        }

        return CommercePayment::query()->create([
            'internal_order_ref'       => $ref,
            'customer_name'            => $data['customer_name'],
            'customer_phone'           => $phone,
            'amount'                   => $amount,
            'currency'                 => $currency,
            'razorpay_payment_link_id' => $body['id'] ?? null,
            'payment_link'             => $body['short_url'] ?? null,
            'status'                   => PaymentLinkStatus::Created,
            'expires_at'               => now()->addHours(self::PAYMENT_LINK_EXPIRY_HOURS),
            'metadata'                 => ['razorpay' => $body],
        ]);
    }

    /**
     * Mark a payment as paid (called from webhook or callback).
     */
    public function markPaid(CommercePayment $payment, string $razorpayPaymentId): CommercePayment
    {
        $payment->update([
            'status'                => PaymentLinkStatus::Paid,
            'razorpay_payment_id'   => $razorpayPaymentId,
            'paid_at'               => now(),
        ]);

        return $payment->fresh();
    }

    /**
     * Update payment status from a Razorpay webhook event type.
     * Reference: https://razorpay.com/docs/payments/payment-link/webhook-events/
     */
    public function handleWebhookEvent(string $event, string $paymentLinkId, ?string $razorpayPaymentId = null): void
    {
        $payment = CommercePayment::query()
            ->where('razorpay_payment_link_id', $paymentLinkId)
            ->first();

        if (! $payment) {
            return;
        }

        $status = match ($event) {
            'payment_link.paid'      => PaymentLinkStatus::Paid,
            'payment_link.expired'   => PaymentLinkStatus::Expired,
            'payment_link.cancelled' => PaymentLinkStatus::Cancelled,
            default                  => null,
        };

        if ($status === null) {
            return;
        }

        $updates = ['status' => $status];

        if ($status === PaymentLinkStatus::Paid && $razorpayPaymentId) {
            $updates['razorpay_payment_id'] = $razorpayPaymentId;
            $updates['paid_at']             = now();
        }

        $payment->update($updates);
    }

    /**
     * Aggregate stats: total paid vs total pending amounts.
     *
     * @return array{total_paid: float, total_pending: float, paid_count: int, pending_count: int}
     */
    public function getStats(): array
    {
        $paid = CommercePayment::query()
            ->where('status', PaymentLinkStatus::Paid)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        $pending = CommercePayment::query()
            ->whereIn('status', [PaymentLinkStatus::Created, PaymentLinkStatus::Sent])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        return [
            'total_paid'    => (float) ($paid?->total ?? 0),
            'total_pending' => (float) ($pending?->total ?? 0),
            'paid_count'    => (int) ($paid?->count ?? 0),
            'pending_count' => (int) ($pending?->count ?? 0),
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function generateOrderRef(): string
    {
        return 'WP-'.time().'-'.random_int(100, 999);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        // Prepend country code if not already present (India = 91)
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        return $digits;
    }
}
