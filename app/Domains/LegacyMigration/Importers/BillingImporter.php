<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\Billing\Models\WalletAutoRechargeSetting;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\WalletTransactionType;
use App\Models\BillingAddress;
use App\Models\Tenant;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;

final class BillingImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'billing';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if ($dryRun) {
            $txCount = $this->legacy->tableExists('wallet_transactions')
                ? (int) $this->legacy->db()->table('wallet_transactions')->where('customer_id', $customer->id)->count()
                : 0;
            $report->bump($this->key(), 'created', 1);
            if ($txCount > 0) {
                $report->bump('wallet_transactions', 'created', $txCount);
            }

            return;
        }

        $wallet = WalletAccount::query()->first();
        if ($wallet === null) {
            $wallet = WalletAccount::query()->create([
                'balance' => (float) ($customer->walletAmount ?? 0),
                'currency' => 'INR',
            ]);
            $report->bump($this->key(), 'created');
        } else {
            $wallet->forceFill([
                'balance' => (float) ($customer->walletAmount ?? $wallet->balance),
            ])->save();
            $report->bump($this->key(), 'updated');
        }

        if (! $this->legacy->tableExists('wallet_transactions')) {
            $report->warn('Legacy table [wallet_transactions] not found; wallet balance only synced.');
            $this->importBillingAddresses($customer, $ids, $report);
            $this->importAutoRecharge($customer, $report);

            return;
        }

        $rows = $this->legacy->db()->table('wallet_transactions')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $amount = abs((float) ($row->amount ?? 0));
            $type = $this->mapType($row->type ?? null, (float) ($row->amount ?? 0));
            $description = $this->buildDescription($row);

            $attributes = [
                'type' => $type,
                'amount' => $amount,
                'currency' => 'INR',
                'balance_after' => 0,
                'description' => $description,
                'reference_type' => 'legacy_wallet_transaction',
                'reference_id' => $legacyId,
                'metadata' => [
                    'legacy_id' => $legacyId,
                    'legacy_type' => $row->type ?? null,
                    'legacy_category' => $row->category_name ?? null,
                    'legacy_msg_id' => $row->msg_id ?? null,
                    'legacy_campaign_id' => $row->campaign_id ?? null,
                    'legacy_sender_name' => $row->sender_name ?? null,
                    'conversion_price_used' => $row->conversion_price_used ?? null,
                ],
                'created_at' => $row->created_at ?? now(),
            ];

            $existingId = $ids->getInt('wallet_tx', $legacyId);
            $existing = null;
            if ($existingId !== null) {
                $existing = WalletTransaction::query()->find($existingId);
            }
            $existing ??= WalletTransaction::query()
                ->where('reference_type', 'legacy_wallet_transaction')
                ->where('reference_id', $legacyId)
                ->first();

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $ids->put('wallet_tx', $legacyId, $existing->id);
                $report->bump('wallet_transactions', 'updated');

                continue;
            }

            $tx = WalletTransaction::query()->create($attributes);
            $ids->put('wallet_tx', $legacyId, $tx->id);
            $report->bump('wallet_transactions', 'created');
        }

        if ($customer->walletAmount !== null) {
            $wallet->forceFill(['balance' => (float) $customer->walletAmount])->save();
        }

        $this->recomputeBalanceAfter((float) ($wallet->fresh()?->balance ?? 0));
        $this->importBillingAddresses($customer, $ids, $report);
        $this->importAutoRecharge($customer, $report);
    }

    private function importBillingAddresses(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
    ): void {
        if (! $this->legacy->tableExists('billing_addresses')) {
            return;
        }

        $rows = $this->legacy->db()->table('billing_addresses')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        $first = true;
        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $company = trim((string) (
                $row->business_legal_name
                ?? $row->business_trade_name
                ?? $row->name
                ?? $row->company_name
                ?? ''
            ));
            $line1 = trim((string) ($row->address ?? $row->address_line_1 ?? ''));

            $attributes = [
                'gst_treatment' => filled($row->gst_treatment ?? null) ? (string) $row->gst_treatment : null,
                'company_name' => $company !== '' ? $company : null,
                'pan' => filled($row->pan ?? null) ? (string) $row->pan : null,
                'email' => filled($row->email ?? null) ? strtolower((string) $row->email) : null,
                'phone' => filled($row->phone ?? null) ? (string) $row->phone : null,
                'address_line_1' => $line1 !== '' ? $line1 : null,
                'address_line_2' => filled($row->address_line_2 ?? null) ? (string) $row->address_line_2 : null,
                'city' => filled($row->city ?? null) ? (string) $row->city : null,
                'state' => filled($row->state ?? null) ? (string) $row->state : null,
                'postal_code' => filled($row->zip ?? $row->postal_code ?? null)
                    ? (string) ($row->zip ?? $row->postal_code)
                    : null,
                'country_code' => $this->resolveCountryCode($row),
                'is_default' => $first,
            ];
            $first = false;

            $existingId = $ids->getInt('billing_address', $legacyId);
            $existing = $existingId
                ? BillingAddress::query()->find($existingId)
                : null;

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $address = $existing;
                $report->bump('billing_addresses', 'updated');
            } else {
                $address = BillingAddress::query()->create($attributes);
                $report->bump('billing_addresses', 'created');
            }

            $ids->put('billing_address', $legacyId, $address->id);
        }
    }

    private function importAutoRecharge(LegacyCustomerSnapshot $customer, MigrationReport $report): void
    {
        if (! $this->legacy->tableExists('wallet_auto_recharge_settings')) {
            return;
        }

        $row = $this->legacy->db()->table('wallet_auto_recharge_settings')
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return;
        }

        $enabled = (bool) ($row->is_enabled ?? false);
        if (isset($row->status)) {
            $status = strtolower((string) $row->status);
            $enabled = $enabled && in_array($status, ['active', '1', 'enabled'], true);
        }

        $lastTriggered = null;
        if (! empty($row->last_recharged_at) && $row->last_recharged_at !== '0000-00-00 00:00:00') {
            try {
                $lastTriggered = Carbon::parse((string) $row->last_recharged_at);
            } catch (\Throwable) {
                $lastTriggered = null;
            }
        }

        $settings = WalletAutoRechargeSetting::query()->first();
        $attributes = [
            'enabled' => $enabled,
            'threshold_amount' => (float) ($row->threshold_amount ?? 0),
            'recharge_amount' => (float) ($row->recharge_amount ?? 0),
            'last_triggered_at' => $lastTriggered,
        ];

        if ($settings !== null) {
            $settings->forceFill($attributes)->save();
            $report->bump('wallet_auto_recharge', 'updated');
        } else {
            WalletAutoRechargeSetting::query()->create($attributes);
            $report->bump('wallet_auto_recharge', 'created');
        }
    }

    private function resolveCountryCode(object $row): ?string
    {
        if (filled($row->country_code ?? null)) {
            return strtoupper(substr((string) $row->country_code, 0, 3));
        }

        if (! isset($row->country_id) || ! $this->legacy->tableExists('countries')) {
            return 'IN';
        }

        $code = $this->legacy->db()->table('countries')
            ->where('id', (int) $row->country_id)
            ->value('code');

        return filled($code) ? strtoupper(substr((string) $code, 0, 3)) : 'IN';
    }

    private function recomputeBalanceAfter(float $currentBalance): void
    {
        $running = $currentBalance;

        foreach (WalletTransaction::query()->orderByDesc('id')->cursor() as $transaction) {
            $amount = abs((float) $transaction->amount);
            $transaction->forceFill(['balance_after' => round($running, 2)])->save();
            $running = $transaction->type === WalletTransactionType::Credit
                ? $running - $amount
                : $running + $amount;
        }
    }

    private function mapType(mixed $type, float $amount): WalletTransactionType
    {
        $value = strtolower(trim((string) $type));

        if (
            str_contains($value, 'credit')
            || str_contains($value, 'top')
            || str_contains($value, 'recharge')
            || str_contains($value, 'add')
        ) {
            return WalletTransactionType::Credit;
        }

        if (
            str_contains($value, 'debit')
            || str_contains($value, 'withdraw')
            || str_contains($value, 'charge')
            || str_contains($value, 'deduct')
            || str_contains($value, 'usage')
        ) {
            return WalletTransactionType::Debit;
        }

        // Legacy often stores debits as negative amounts.
        return $amount < 0 ? WalletTransactionType::Debit : WalletTransactionType::Credit;
    }

    private function buildDescription(object $row): string
    {
        $description = trim((string) ($row->description ?? ''));
        if ($description !== '') {
            return $description;
        }

        $category = trim((string) ($row->category_name ?? ''));
        $type = trim((string) ($row->type ?? ''));

        if ($category !== '' && $type !== '') {
            return "{$type} · {$category}";
        }

        if ($category !== '') {
            return $category;
        }

        if ($type !== '') {
            return $type;
        }

        if (! empty($row->campaign_id)) {
            return 'Campaign / Chatbot withdrawal';
        }

        return 'Wallet transaction';
    }
}
