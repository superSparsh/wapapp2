<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\WalletTransactionType;
use App\Models\Tenant;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;

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

            return;
        }

        $running = 0.0;
        $rows = $this->legacy->db()->table('wallet_transactions')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $amount = abs((float) ($row->amount ?? 0));
            $type = $this->mapType($row->type ?? null, (float) ($row->amount ?? 0));
            $description = $this->buildDescription($row);

            $running = $type === WalletTransactionType::Credit
                ? $running + $amount
                : $running - $amount;

            $attributes = [
                'type' => $type,
                'amount' => $amount,
                'currency' => 'INR',
                'balance_after' => $running,
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
