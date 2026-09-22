<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\Commerce\Models\PaymentConfig;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\Template;
use App\Models\Tenant;

/**
 * Legacy commerce payment configs (Razorpay) → commerce_payment_configs.
 * Order history is skipped (legacy orders lack reliable customer scoping).
 */
final class CommerceImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'commerce';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('payment_configs')) {
            return;
        }

        $rows = $this->legacy->db()->table('payment_configs')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $clientName = trim((string) ($row->client_name ?? 'Default'));
            $key = trim((string) ($row->razorpay_key ?? ''));
            $secret = (string) ($row->razorpay_secret ?? '');

            $existingId = $ids->getInt('payment_config', $legacyId);
            $existing = $existingId
                ? PaymentConfig::query()->find($existingId)
                : PaymentConfig::query()->where('client_name', $clientName)->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $attributes = [
                'client_name' => $clientName !== '' ? $clientName : 'Default',
                'razorpay_key' => $key !== '' ? $key : null,
                'razorpay_secret' => $secret !== '' ? $secret : null,
                'payment_template_id' => $this->resolveTemplateId($row->template ?? null, $ids),
                'confirmation_template_id' => $this->resolveTemplateId($row->confirm_payment_template ?? null, $ids),
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $config = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $config = PaymentConfig::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('payment_config', $legacyId, $config->id);
        }
    }

    private function resolveTemplateId(mixed $raw, MigrationIdMap $ids): ?int
    {
        if (! filled($raw)) {
            return null;
        }

        if (is_numeric($raw)) {
            return $ids->getInt('template', (int) $raw)
                ?? Template::query()->where('id', (int) $raw)->value('id');
        }

        $value = trim((string) $raw);

        return Template::query()
            ->where('code', $value)
            ->orWhere('name', $value)
            ->value('id');
    }
}
