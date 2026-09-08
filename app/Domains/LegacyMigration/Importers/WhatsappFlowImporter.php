<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\WhatsappFlowStatus;
use App\Models\Tenant;
use App\Models\WhatsappFlow;

final class WhatsappFlowImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'whatsapp_flows';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('flows')) {
            return;
        }

        $rows = $this->legacy->db()->table('flows')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->name ?? 'Untitled Flow'));
            $existingId = $ids->getInt('whatsapp_flow', $legacyId);
            $existing = $existingId
                ? WhatsappFlow::query()->find($existingId)
                : WhatsappFlow::query()->where('name', $name)->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $flowJson = $this->decodeToArray($row->json_code ?? $row->og_json_code ?? null)
                ?? $this->decodeToArray($row->screens ?? null);

            $lineId = $row->new_contact_id ? $ids->getInt('line', (int) $row->new_contact_id) : null;

            $attributes = [
                'name' => $name,
                'status' => $this->mapStatus($row->status ?? null),
                'meta_flow_id' => $row->flow_id ? (string) $row->flow_id : null,
                'flow_json' => $flowJson,
                'whatsapp_line_id' => $lineId,
                'cust_space_id' => $row->cust_space_id ?? null,
                'published_at' => $this->mapStatus($row->status ?? null) === WhatsappFlowStatus::Active ? now() : null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $flow = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $flow = WhatsappFlow::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('whatsapp_flow', $legacyId, $flow->id);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeToArray(mixed $raw): ?array
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

    private function mapStatus(mixed $status): WhatsappFlowStatus
    {
        $value = strtolower((string) $status);

        return match (true) {
            in_array($value, ['published', 'active', '1'], true) => WhatsappFlowStatus::Active,
            in_array($value, ['archived', 'deleted'], true) => WhatsappFlowStatus::Archived,
            default => WhatsappFlowStatus::Draft,
        };
    }
}
