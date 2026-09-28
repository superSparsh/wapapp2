<?php

declare(strict_types=1);

namespace App\Domains\Billing\Jobs;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Billing\Services\BillingAddressService;
use App\Domains\Billing\Services\WalletService;
use App\Domains\Billing\Services\Zoho\ZohoBooksWalletCreditService;
use App\Models\RazorpayOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WalletTransaction;
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
        WalletService $walletService,
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

            $creditRequest = tenancy()->central(function () {
                return ZohoWalletCreditRequest::query()
                    ->where('tenant_id', $this->tenantId)
                    ->where('source', 'razorpay')
                    ->where('external_id', $this->paymentId)
                    ->first();
            });

            $invoiceAmount = (float) $order->amount;
            $taxAmount = (float) ($order->tax_amount ?? 0);
            $payableAmount = (float) ($order->total_amount ?? ($invoiceAmount + $taxAmount));

            if ($creditRequest === null) {
                $creditRequest = tenancy()->central(function () use ($invoiceAmount, $taxAmount, $payableAmount, $order) {
                    return ZohoWalletCreditRequest::query()->create([
                        'tenant_id' => $this->tenantId,
                        'source' => 'razorpay',
                        'amount' => $invoiceAmount,
                        'currency' => (string) ($order->currency ?: 'INR'),
                        'status' => 'pending',
                        'external_id' => $this->paymentId,
                        'wallet_credited_at' => now(),
                        'metadata' => [
                            'razorpay_order_id' => $order->razorpay_order_id,
                            'razorpay_payment_id' => $this->paymentId,
                            'local_order_id' => $order->id,
                            'wallet_credit_amount' => $invoiceAmount,
                            'tax_amount' => $taxAmount,
                            'payable_amount' => $payableAmount,
                        ],
                    ]);
                });
            }

            $alreadySynced = filled($creditRequest->invoice_number)
                && $creditRequest->status === 'synced'
                && filled(data_get($creditRequest->metadata, 'zoho_invoice_id'));

            $user = $this->resolveUser($tenant);
            $invoice = [
                'invoice_id' => data_get($creditRequest->metadata, 'zoho_invoice_id'),
                'invoice_number' => $creditRequest->invoice_number,
                'invoice_url' => data_get($creditRequest->metadata, 'zoho_invoice_url'),
                'contact_id' => data_get($creditRequest->metadata, 'zoho_contact_id'),
            ];

            if (! $alreadySynced) {
                $billing = $billingAddressService->default();

                $invoice = $zohoBooks->createPaidWalletCreditInvoice(
                    tenant: $tenant,
                    user: $user,
                    amount: $invoiceAmount,
                    referenceNumber: 'WRP-'.$order->id,
                    paymentReference: $this->paymentId,
                    billingAddress: $billing,
                );

                $creditRequestId = (int) $creditRequest->id;
                $creditRequest = tenancy()->central(function () use ($creditRequestId, $invoice, $taxAmount, $payableAmount) {
                    $fresh = ZohoWalletCreditRequest::query()->find($creditRequestId);
                    if ($fresh === null) {
                        return null;
                    }

                    $fresh->forceFill([
                        'status' => 'synced',
                        'invoice_number' => $invoice['invoice_number'] ?? $fresh->invoice_number,
                        'wallet_credited_at' => $fresh->wallet_credited_at ?? now(),
                        'last_error' => null,
                        'metadata' => array_merge($fresh->metadata ?? [], [
                            'zoho_invoice_id' => $invoice['invoice_id'] ?? null,
                            'zoho_invoice_url' => $invoice['invoice_url'] ?? null,
                            'zoho_contact_id' => $invoice['contact_id'] ?? null,
                            'zoho_synced_at' => now()->toIso8601String(),
                            'tax_amount' => $taxAmount,
                            'payable_amount' => $payableAmount,
                        ]),
                    ])->save();

                    return $fresh->fresh();
                }) ?? $creditRequest;
            }

            $this->sendCompletionEmailOnce(
                $creditRequest,
                $user,
                $order,
                $invoice,
                $walletService,
            );
        } catch (Throwable $e) {
            tenancy()->central(function () use ($e): void {
                ZohoWalletCreditRequest::query()
                    ->where('tenant_id', $this->tenantId)
                    ->where('source', 'razorpay')
                    ->where('external_id', $this->paymentId)
                    ->update([
                        'status' => 'failed',
                        'last_error' => $e->getMessage(),
                    ]);
            });

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

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function sendCompletionEmailOnce(
        ZohoWalletCreditRequest $creditRequest,
        User $user,
        RazorpayOrder $order,
        array $invoice,
        WalletService $walletService,
    ): void {
        $claimed = tenancy()->central(function () use ($creditRequest): bool {
            return ZohoWalletCreditRequest::query()
                ->whereKey($creditRequest->id)
                ->whereNull('completion_email_sent_at')
                ->update(['completion_email_sent_at' => now()]) === 1;
        });

        if (! $claimed) {
            return;
        }

        $creditAmount = round((float) $order->amount, 2);
        $taxAmount = round((float) ($order->tax_amount ?? 0), 2);
        $payableAmount = round((float) ($order->total_amount ?? ($creditAmount + $taxAmount)), 2);

        $balance = round((float) $walletService->balance(), 2);
        $previousBalance = $this->resolvePreviousBalance($creditAmount, $balance);

        $invoiceUrl = (string) ($invoice['invoice_url'] ?? data_get($creditRequest->metadata, 'zoho_invoice_url') ?? '');
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? $creditRequest->invoice_number ?? '');

        try {
            app(AlertDispatcher::class)->walletCreditCompleted([
                'customer_email' => $user->email,
                'customer_name' => $user->name,
                'customer_id' => $this->tenantId,
                'amount' => $creditAmount,
                'currency' => 'INR',
                'amount_display' => '₹'.number_format($creditAmount, 2),
                'gst_amount' => $taxAmount,
                'gst_display' => '₹'.number_format($taxAmount, 2),
                'paid_amount' => $payableAmount,
                'paid_display' => '₹'.number_format($payableAmount, 2),
                'previous_balance' => $previousBalance,
                'previous_balance_display' => '₹'.number_format($previousBalance, 2),
                'balance' => $balance,
                'balance_display' => '₹'.number_format($balance, 2),
                'payment_id' => $this->paymentId,
                'invoice_id' => $invoiceNumber,
                'invoice_number' => $invoiceNumber,
                'invoice_url' => $invoiceUrl !== '' ? $invoiceUrl : null,
                'zoho_invoice_id' => $invoice['invoice_id'] ?? data_get($creditRequest->metadata, 'zoho_invoice_id'),
                'reference' => 'WRP-'.$order->id,
            ]);
        } catch (Throwable $alertError) {
            // Allow a later reconcile pass to retry the email.
            tenancy()->central(function () use ($creditRequest): void {
                ZohoWalletCreditRequest::query()
                    ->whereKey($creditRequest->id)
                    ->update(['completion_email_sent_at' => null]);
            });

            Log::warning('Wallet credit completed alert failed', ['error' => $alertError->getMessage()]);
        }
    }

    private function resolvePreviousBalance(float $creditAmount, float $currentBalance): float
    {
        $txn = WalletTransaction::query()
            ->where('razorpay_payment_id', $this->paymentId)
            ->latest('id')
            ->first();

        if ($txn !== null) {
            return max(0, round((float) $txn->balance_after - (float) $txn->amount, 2));
        }

        return max(0, round($currentBalance - $creditAmount, 2));
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
