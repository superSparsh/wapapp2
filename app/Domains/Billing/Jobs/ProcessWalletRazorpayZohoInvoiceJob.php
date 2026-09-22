<?php

declare(strict_types=1);

namespace App\Domains\Billing\Jobs;

use App\Domains\Billing\Services\BillingAddressService;
use App\Domains\Billing\Services\Zoho\ZohoBooksWalletCreditService;
use App\Models\RazorpayOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWalletRazorpayZohoInvoiceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $tenantId,
        public readonly int $razorpayOrderId,
        public readonly string $paymentId,
        public readonly ?int $userId = null,
    ) {}

    public function handle(
        ZohoBooksWalletCreditService $zohoBooks,
        BillingAddressService $billingAddressService,
    ): void {
        if (! $zohoBooks->isConfigured()) {
            Log::info('Zoho wallet invoice skipped: not configured', [
                'tenant_id' => $this->tenantId,
                'order_id' => $this->razorpayOrderId,
            ]);

            return;
        }

        /** @var Tenant|null $tenant */
        $tenant = tenancy()->central(fn () => Tenant::query()->find($this->tenantId));
        if (! $tenant) {
            Log::warning('Zoho wallet invoice: tenant missing', ['tenant_id' => $this->tenantId]);

            return;
        }

        $alreadyOnTenant = tenancy()->initialized
            && (string) tenant('id') === (string) $this->tenantId;

        if (! $alreadyOnTenant) {
            tenancy()->initialize($tenant);
        }

        try {
            $order = RazorpayOrder::query()->find($this->razorpayOrderId);
            if (! $order) {
                return;
            }

            $existing = ZohoWalletCreditRequest::query()
                ->where('tenant_id', $this->tenantId)
                ->where('source', 'razorpay')
                ->where('external_id', $this->paymentId)
                ->first();

            if ($existing && filled($existing->invoice_number) && $existing->status === 'synced') {
                return;
            }

            // Legacy: Zoho invoice line = wallet credit (base). GST via ZOHO_WALLET_LINE_TAX_ID when set.
            $invoiceAmount = (float) $order->amount;

            $creditRequest = $existing ?? ZohoWalletCreditRequest::query()->create([
                'tenant_id' => $this->tenantId,
                'source' => 'razorpay',
                'amount' => $invoiceAmount,
                'currency' => (string) ($order->currency ?: 'INR'),
                'status' => 'pending',
                'external_id' => $this->paymentId,
                'metadata' => [
                    'razorpay_order_id' => $order->razorpay_order_id,
                    'razorpay_payment_id' => $this->paymentId,
                    'local_order_id' => $order->id,
                    'wallet_credit_amount' => (float) $order->amount,
                    'tax_amount' => (float) ($order->tax_amount ?? 0),
                    'payable_amount' => (float) ($order->total_amount ?? $order->amount),
                ],
            ]);

            $user = $this->resolveUser($tenant);
            $billing = $billingAddressService->default();

            $invoice = $zohoBooks->createPaidWalletCreditInvoice(
                tenant: $tenant,
                user: $user,
                amount: $invoiceAmount,
                referenceNumber: 'WRP-'.$order->id,
                paymentReference: $this->paymentId,
                billingAddress: $billing,
            );

            $creditRequest->forceFill([
                'status' => 'synced',
                'invoice_number' => $invoice['invoice_number'],
                'wallet_credited_at' => $creditRequest->wallet_credited_at ?? now(),
                'last_error' => null,
                'metadata' => array_merge($creditRequest->metadata ?? [], [
                    'zoho_invoice_id' => $invoice['invoice_id'],
                    'zoho_invoice_url' => $invoice['invoice_url'],
                    'zoho_contact_id' => $invoice['contact_id'],
                    'zoho_synced_at' => now()->toIso8601String(),
                ]),
            ])->save();

            try {
                app(\App\Domains\Alerts\Services\AlertDispatcher::class)->walletCreditCompleted([
                    'customer_email' => $user->email,
                    'customer_name' => $user->name,
                    'amount' => $invoiceAmount,
                    'currency' => 'INR',
                    'payment_id' => $this->paymentId,
                    'invoice_id' => $invoice['invoice_number'] ?? null,
                    'balance' => app(\App\Domains\Billing\Services\WalletService::class)->balance(),
                ]);
            } catch (Throwable $alertError) {
                Log::warning('Wallet credit completed alert failed', ['error' => $alertError->getMessage()]);
            }
        } catch (Throwable $e) {
            ZohoWalletCreditRequest::query()
                ->where('tenant_id', $this->tenantId)
                ->where('source', 'razorpay')
                ->where('external_id', $this->paymentId)
                ->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                ]);

            Log::error('Zoho wallet invoice failed', [
                'tenant_id' => $this->tenantId,
                'order_id' => $this->razorpayOrderId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if (! $alreadyOnTenant && tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    private function resolveUser(Tenant $tenant): User
    {
        if ($this->userId) {
            $user = User::query()->find($this->userId);
            if ($user) {
                return $user;
            }
        }

        $authUser = Auth::user();
        if ($authUser instanceof User) {
            return $authUser;
        }

        $fallback = new User;
        $fallback->forceFill([
            'name' => (string) ($tenant->name ?: $tenant->company_name ?: 'WapApp Customer'),
            'email' => (string) ($tenant->email ?: ''),
            'phone' => $tenant->phone,
        ]);

        return $fallback;
    }
}
