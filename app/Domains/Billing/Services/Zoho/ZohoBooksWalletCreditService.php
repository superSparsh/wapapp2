<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services\Zoho;

use App\Models\BillingAddress;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Zoho Books integration for wallet top-ups (legacy Razorpay → paid invoice parity).
 */
class ZohoBooksWalletCreditService
{
    public function isConfigured(): bool
    {
        return filled(config('services.zoho.invoice_refresh_token'))
            && filled(config('services.zoho.client_id'))
            && filled(config('services.zoho.client_secret'))
            && filled(config('services.zoho.organization_id'));
    }

    /**
     * Create a Zoho Books invoice and record the Razorpay payment as paid.
     *
     * @return array{invoice_id: string, invoice_number: ?string, invoice_url: ?string, contact_id: string}
     */
    public function createPaidWalletCreditInvoice(
        Tenant $tenant,
        User $user,
        float $amount,
        string $referenceNumber,
        string $paymentReference,
        ?BillingAddress $billingAddress = null,
        ?string $paymentMode = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Zoho Books is not configured.');
        }

        $accessToken = $this->refreshAccessToken();
        $contactId = $this->resolveOrCreateContactId($accessToken, $tenant, $user, $billingAddress);
        $amount = round($amount, 2);

        $line = $this->buildLineItem($amount);
        $line['description'] = 'WAPAPP Wallet Credits Top-up (Razorpay)';

        $orgId = $this->organizationId();
        $payload = [
            'customer_id' => $contactId,
            'reference_number' => $referenceNumber,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->format('Y-m-d'),
            'line_items' => [$line],
        ];

        $currencyId = config('services.zoho.wallet_invoice_currency_id');
        if (filled($currencyId)) {
            $payload['currency_id'] = $currencyId;
        }

        $url = $this->booksBaseUrl().'/invoices?organization_id='.rawurlencode($orgId);
        $response = Http::withHeaders($this->orgHeaders($accessToken))->timeout(60)->post($url, $payload);

        if (! $response->successful()) {
            $body = $response->json();
            $message = is_array($body) ? ($body['message'] ?? json_encode($body)) : $response->body();
            throw new RuntimeException('Zoho invoice create failed: '.$message);
        }

        $invoice = $response->json()['invoice'] ?? null;
        if (! is_array($invoice) || empty($invoice['invoice_id'])) {
            throw new RuntimeException('Zoho invoice create returned no invoice_id.');
        }

        $this->assertInvoiceAmount($invoice, $amount);

        $zohoInvoiceId = (string) $invoice['invoice_id'];
        $invoiceNumber = isset($invoice['invoice_number']) ? (string) $invoice['invoice_number'] : null;
        $invoiceTotal = (float) ($invoice['total'] ?? $amount);

        $this->markInvoiceAsSent($accessToken, $zohoInvoiceId);
        $this->recordCustomerPayment(
            $accessToken,
            $contactId,
            $zohoInvoiceId,
            $invoiceTotal,
            $invoiceNumber ?? $zohoInvoiceId,
            $paymentReference,
            $paymentMode ?? (string) config('services.zoho.wallet_razorpay_payment_mode', 'razorpay'),
        );

        $latestInvoice = $this->fetchInvoice($accessToken, $zohoInvoiceId);

        return [
            'invoice_id' => $zohoInvoiceId,
            'invoice_number' => $invoiceNumber,
            'invoice_url' => $this->extractInvoiceUrl($latestInvoice),
            'contact_id' => $contactId,
        ];
    }

    public function refreshAccessToken(): string
    {
        $refreshToken = config('services.zoho.invoice_refresh_token');
        $clientId = config('services.zoho.client_id');
        $clientSecret = config('services.zoho.client_secret');

        if (! $refreshToken || ! $clientId || ! $clientSecret) {
            throw new RuntimeException('Zoho OAuth credentials are not configured.');
        }

        $accountsHost = rtrim((string) config('services.zoho.accounts_host', 'https://accounts.zoho.com'), '/');
        $response = Http::asForm()->timeout(45)->post($accountsHost.'/oauth/v2/token', [
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
        ]);

        $data = $response->json();
        if (! $response->successful() || empty($data['access_token'])) {
            $msg = $data['error_description'] ?? $data['error'] ?? $response->body();
            throw new RuntimeException('Zoho token request failed: '.$msg);
        }

        return (string) $data['access_token'];
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchInvoice(string $accessToken, string $zohoInvoiceId): array
    {
        $orgId = $this->organizationId();
        $url = $this->booksBaseUrl().'/invoices/'.rawurlencode($zohoInvoiceId).'?organization_id='.rawurlencode($orgId);
        $response = Http::withHeaders($this->orgHeaders($accessToken))->timeout(45)->get($url);
        if (! $response->successful()) {
            throw new RuntimeException('Zoho invoice fetch failed: HTTP '.$response->status());
        }
        $json = $response->json();
        if (empty($json['invoice']) || ! is_array($json['invoice'])) {
            throw new RuntimeException('Zoho invoice response missing invoice object.');
        }

        return $json['invoice'];
    }

    public function recordCustomerPayment(
        string $accessToken,
        string $contactId,
        string $zohoInvoiceId,
        float $amount,
        string $invoiceNumber,
        string $paymentReference,
        string $paymentMode,
    ): void {
        $orgId = $this->organizationId();
        $payload = [
            'customer_id' => $contactId,
            'payment_mode' => $paymentMode,
            'amount' => round($amount, 2),
            'date' => now()->toDateString(),
            'reference_number' => $paymentReference,
            'description' => 'Razorpay wallet top-up — '.$invoiceNumber,
            'invoices' => [
                [
                    'invoice_id' => $zohoInvoiceId,
                    'amount_applied' => round($amount, 2),
                ],
            ],
            'bank_charges' => 0,
        ];

        $url = $this->booksBaseUrl().'/customerpayments?organization_id='.rawurlencode($orgId);
        $response = Http::withHeaders($this->orgHeaders($accessToken))->timeout(60)->post($url, $payload);

        if (! $response->successful()) {
            $body = $response->json();
            $message = is_array($body) ? ($body['message'] ?? json_encode($body)) : $response->body();
            throw new RuntimeException('Zoho customer payment failed: '.$message);
        }
    }

    private function booksBaseUrl(): string
    {
        return rtrim((string) config('services.zoho.books_api_base', 'https://www.zohoapis.com/books/v3'), '/');
    }

    private function organizationId(): string
    {
        $id = (string) config('services.zoho.organization_id', '');
        if ($id === '') {
            throw new RuntimeException('Zoho organization id is not configured.');
        }

        return $id;
    }

    /**
     * @return array<string, string>
     */
    private function orgHeaders(string $accessToken): array
    {
        return [
            'Authorization' => 'Zoho-oauthtoken '.$accessToken,
            'X-com-zoho-invoice-organizationid' => $this->organizationId(),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLineItem(float $amount): array
    {
        $line = [
            'name' => (string) config('services.zoho.wallet_line_name', 'WAPAPP Wallet Credits'),
            'description' => 'WAPAPP Wallet Credits Top-up',
            'rate' => round($amount, 4),
            'quantity' => 1,
            'item_total' => round($amount, 4),
        ];

        $taxId = config('services.zoho.wallet_line_tax_id');
        if (filled($taxId)) {
            $line['tax_id'] = $taxId;
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function assertInvoiceAmount(array $invoice, float $expectedAmount): void
    {
        $lineItems = $invoice['line_items'] ?? [];
        $rate = 0.0;
        if (is_array($lineItems) && isset($lineItems[0]) && is_array($lineItems[0])) {
            $rate = (float) ($lineItems[0]['rate'] ?? 0);
        }
        $total = (float) ($invoice['total'] ?? 0);

        if ($rate <= 0 || $total <= 0) {
            throw new RuntimeException('Zoho invoice amount is zero. Please verify line item and tax configuration.');
        }

        if (abs($rate - $expectedAmount) >= 0.009) {
            throw new RuntimeException('Zoho invoice amount mismatch. Expected '.$expectedAmount.', got '.$rate);
        }
    }

    /**
     * @param  array<string, mixed>  $invoice
     */
    private function extractInvoiceUrl(array $invoice): ?string
    {
        foreach (['invoice_url', 'url', 'permalink'] as $key) {
            if (! empty($invoice[$key]) && is_string($invoice[$key])) {
                return $invoice[$key];
            }
        }

        return null;
    }

    private function markInvoiceAsSent(string $accessToken, string $zohoInvoiceId): void
    {
        $orgId = $this->organizationId();
        $url = $this->booksBaseUrl().'/invoices/'.rawurlencode($zohoInvoiceId).'/status/sent?organization_id='.rawurlencode($orgId);
        $response = Http::withHeaders($this->orgHeaders($accessToken))->timeout(45)->post($url);

        if (! $response->successful()) {
            Log::warning('Zoho mark invoice sent failed', [
                'invoice_id' => $zohoInvoiceId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function resolveOrCreateContactId(
        string $accessToken,
        Tenant $tenant,
        User $user,
        ?BillingAddress $billingAddress,
    ): string {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $cached = trim((string) ($settings['zoho_books_contact_id'] ?? ''));
        if ($cached !== '') {
            return $cached;
        }

        $email = trim((string) ($user->email ?: $tenant->email ?: ''));
        $found = $email !== '' ? $this->searchContactIdByEmail($accessToken, $email) : null;
        if ($found) {
            $this->persistTenantZohoContact($tenant, $found);

            return $found;
        }

        $created = $this->createContactInZohoBooks($accessToken, $tenant, $user, $billingAddress);
        if (! $created) {
            throw new RuntimeException('Unable to create Zoho Books contact for this account.');
        }

        $this->persistTenantZohoContact($tenant, $created);

        return $created;
    }

    private function persistTenantZohoContact(Tenant $tenant, string $contactId): void
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        if (($settings['zoho_books_contact_id'] ?? null) === $contactId) {
            return;
        }

        $settings['zoho_books_contact_id'] = $contactId;
        $tenant->forceFill(['settings' => $settings])->save();
    }

    private function searchContactIdByEmail(string $accessToken, string $email): ?string
    {
        $orgId = $this->organizationId();
        $url = $this->booksBaseUrl().'/contacts?organization_id='.rawurlencode($orgId)
            .'&search_text='.rawurlencode($email);

        $response = Http::withHeaders($this->orgHeaders($accessToken))->timeout(45)->get($url);
        if (! $response->successful()) {
            Log::warning('Zoho Books contact search failed', ['status' => $response->status()]);

            return null;
        }

        $contacts = $response->json()['contacts'] ?? [];
        if (! is_array($contacts)) {
            return null;
        }

        $needle = strtolower($email);
        foreach ($contacts as $row) {
            if (! is_array($row) || empty($row['contact_id'])) {
                continue;
            }
            $rowEmail = strtolower(trim((string) ($row['email'] ?? '')));
            if ($rowEmail === $needle) {
                return (string) $row['contact_id'];
            }
        }

        return null;
    }

    private function createContactInZohoBooks(
        string $accessToken,
        Tenant $tenant,
        User $user,
        ?BillingAddress $billingAddress,
    ): ?string {
        $name = trim((string) ($user->name ?: $tenant->name ?: $tenant->company_name ?: 'WapApp Customer'));
        $email = trim((string) ($user->email ?: $tenant->email ?: ''));
        $phone = trim((string) ($user->phone ?? $tenant->phone ?? $billingAddress?->phone ?? ''));

        $contactData = [
            'contact_name' => $name !== '' ? $name : 'WapApp Customer',
            'company_name' => (string) ($billingAddress?->company_name ?: $tenant->company_name ?: $name),
            'email' => $email,
            'phone' => $phone,
            'mobile' => $phone,
        ];

        if ($billingAddress) {
            $contactData['billing_address'] = [
                'attention' => $name,
                'address' => trim(implode(', ', array_filter([
                    $billingAddress->address_line_1,
                    $billingAddress->address_line_2,
                ]))),
                'city' => (string) ($billingAddress->city ?? ''),
                'state' => (string) ($billingAddress->state ?? ''),
                'zip' => (string) ($billingAddress->postal_code ?? ''),
                'phone' => (string) ($billingAddress->phone ?? $phone),
            ];
            $contactData['shipping_address'] = $contactData['billing_address'];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $contactData['contact_persons'] = [[
            'first_name' => $parts[0] ?? $name,
            'last_name' => $parts[1] ?? '',
            'email' => $email,
            'mobile' => $phone,
            'is_primary_contact' => true,
        ]];

        $orgId = $this->organizationId();
        $url = $this->booksBaseUrl().'/contacts?organization_id='.rawurlencode($orgId);
        $response = Http::withHeaders($this->orgHeaders($accessToken))->timeout(60)->post($url, $contactData);

        if (! $response->successful()) {
            Log::error('Zoho Books contact create failed', ['body' => $response->body()]);

            return null;
        }

        $contactId = $response->json('contact.contact_id');

        return $contactId ? (string) $contactId : null;
    }
}
