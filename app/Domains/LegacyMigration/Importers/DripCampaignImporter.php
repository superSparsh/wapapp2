<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\ChatbotFlowStatus;
use App\Models\DripCampaign;
use App\Models\Tenant;

final class DripCampaignImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'drips';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('automation2s')) {
            return;
        }

        $rows = $this->legacy->db()->table('automation2s')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->name ?? 'Untitled Drip'));
            $existingId = $ids->getInt('drip', $legacyId);
            $existing = $existingId
                ? DripCampaign::query()->find($existingId)
                : DripCampaign::query()->where('name', $name)->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $exported = $this->decodeExported($row->data ?? null);
            $audienceId = $row->mail_list_id ? $ids->getInt('list', (int) $row->mail_list_id) : null;

            $attributes = [
                'name' => $name,
                'status' => $this->mapStatus($row->status ?? null),
                'exported_data' => $exported,
                'timezone' => $row->time_zone ?: 'Asia/Kolkata',
                'start_date' => $row->start_date ?? null,
                'end_date' => $row->end_date ?? null,
                'audience_id' => $audienceId,
                'published_at' => $this->mapStatus($row->status ?? null) === ChatbotFlowStatus::Active ? now() : null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $drip = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $drip = DripCampaign::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('drip', $legacyId, $drip->id);
        }
    }

    private function decodeExported(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function mapStatus(mixed $status): ChatbotFlowStatus
    {
        $value = strtolower((string) $status);

        return match (true) {
            in_array($value, ['active', 'enabled', '1', 'running'], true) => ChatbotFlowStatus::Active,
            in_array($value, ['paused', 'inactive', '0'], true) => ChatbotFlowStatus::Inactive,
            default => ChatbotFlowStatus::Draft,
        };
    }
}
