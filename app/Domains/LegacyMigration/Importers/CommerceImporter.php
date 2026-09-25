<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Enums\PaymentStatus;
use App\Domains\Commerce\Models\CommerceOrder;
use App\Domains\Commerce\Models\PaymentConfig;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\WhatsappLine;
use Illuminate\Support\Carbon;

/**
 * Legacy payment configs + WhatsApp commerce orders → tenant commerce tables.
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
        $this->importPaymentConfigs($customer, $ids, $report, $dryRun);
        $this->importOrders($customer, $ids, $report, $dryRun);
    }

    private function importPaymentConfigs(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
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

    private function importOrders(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('orders')) {
            return;
        }

        $wabaIds = $this->customerWabaIds($customer->id);
        if ($wabaIds === []) {
            $report->warn("Commerce orders skipped: no WABA ID for customer #{$customer->id}.");

            return;
        }

        $rows = $this->legacy->db()->table('orders')
            ->whereIn('waba_id', $wabaIds)
            ->orderBy('id')
            ->get();

        $lineByWaba = WhatsappLine::query()
            ->whereIn('waba_id', $wabaIds)
            ->get(['id', 'waba_id'])
            ->keyBy(static fn (WhatsappLine $line): string => (string) $line->waba_id);

        $defaultLineId = WhatsappLine::query()->where('is_default', true)->value('id')
            ?? WhatsappLine::query()->orderBy('id')->value('id');

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $messageId = trim((string) ($row->message_id ?? ''));
            $wabaId = trim((string) ($row->waba_id ?? ''));

            $existingId = $ids->getInt('commerce_order', $legacyId);
            $existing = $existingId
                ? CommerceOrder::query()->find($existingId)
                : null;

            if ($existing === null && $messageId !== '') {
                $existing = CommerceOrder::query()
                    ->where('external_message_id', $messageId)
                    ->first();
            }

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $lineId = $lineByWaba->get($wabaId)?->id ?? $defaultLineId;
            $attributes = [
                'catalog_id' => filled($row->catalog_id ?? null) ? (string) $row->catalog_id : null,
                'customer_name' => filled($row->customer_name ?? null) ? (string) $row->customer_name : 'Unknown',
                'customer_phone' => filled($row->customer_phone ?? null) ? (string) $row->customer_phone : null,
                'product_items' => $this->decodeProductItems($row->product_items ?? null),
                'total_price' => (float) ($row->total_price ?? 0),
                'currency' => strtoupper(substr((string) ($row->currency ?? 'INR'), 0, 3)) ?: 'INR',
                'order_status' => $this->mapOrderStatus($row->order_status ?? null),
                'payment_status' => $this->mapPaymentStatus($row->payment_status ?? null),
                'whatsapp_line_id' => $lineId ? (int) $lineId : null,
                'external_message_id' => $messageId !== '' ? $messageId : null,
                'payment_link' => filled($row->payment_link ?? null) ? (string) $row->payment_link : null,
                'metadata' => [
                    'legacy_order_id' => $legacyId,
                    'waba_id' => $wabaId !== '' ? $wabaId : null,
                ],
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $order = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $order = CommerceOrder::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $this->preserveTimestamps($order, $row->created_at ?? null, $row->updated_at ?? null);
            $ids->put('commerce_order', $legacyId, $order->id);
        }
    }

    /**
     * @return list<string>
     */
    private function customerWabaIds(int $customerId): array
    {
        if (! $this->legacy->tableExists('business_infos')) {
            return [];
        }

        $query = $this->legacy->db()->table('business_infos');
        if ($this->legacy->hasColumn('business_infos', 'customer_id')) {
            $query->where('customer_id', $customerId);
        }

        return $query
            ->orderBy('id')
            ->pluck('waba_id')
            ->map(static fn ($id): string => trim((string) $id))
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeProductItems(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter($raw, 'is_array'));
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_array'))
            : [];
    }

    private function mapOrderStatus(mixed $raw): OrderStatus
    {
        return match (strtolower(trim((string) $raw))) {
            'confirmed' => OrderStatus::Confirmed,
            'shipped', 'processing' => OrderStatus::Shipped,
            'delivered' => OrderStatus::Delivered,
            'cancelled', 'canceled' => OrderStatus::Cancelled,
            default => OrderStatus::New,
        };
    }

    private function mapPaymentStatus(mixed $raw): PaymentStatus
    {
        return match (strtolower(trim((string) $raw))) {
            'paid', 'captured', 'success' => PaymentStatus::Paid,
            'failed', 'cancelled', 'canceled' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };
    }

    private function preserveTimestamps(CommerceOrder $order, mixed $createdAt, mixed $updatedAt): void
    {
        $created = $this->parseTimestamp($createdAt);
        $updated = $this->parseTimestamp($updatedAt) ?? $created;

        if ($created === null) {
            return;
        }

        CommerceOrder::query()->whereKey($order->id)->update([
            'created_at' => $created,
            'updated_at' => $updated ?? $created,
        ]);
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
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
