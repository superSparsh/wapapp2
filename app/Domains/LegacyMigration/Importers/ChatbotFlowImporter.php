<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\ChatbotFlowStatus;
use App\Models\ChatbotFlow;
use App\Models\Tenant;

final class ChatbotFlowImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'chatbots';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('automation_bots')) {
            return;
        }

        $rows = $this->legacy->db()->table('automation_bots')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->name ?? 'Untitled Chatbot'));
            $existingId = $ids->getInt('chatbot', $legacyId);
            $existing = $existingId
                ? ChatbotFlow::query()->find($existingId)
                : ChatbotFlow::query()->where('name', $name)->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $exported = $this->decodeExported($row->exported_data ?? null);
            $lineId = $row->new_contact_id ? $ids->getInt('line', (int) $row->new_contact_id) : null;

            $attributes = [
                'name' => $name,
                'status' => $this->mapStatus($row->status ?? null),
                'exported_data' => $exported,
                'whatsapp_line_id' => $lineId,
                'published_at' => $this->mapStatus($row->status ?? null) === ChatbotFlowStatus::Active ? now() : null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $flow = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $flow = ChatbotFlow::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('chatbot', $legacyId, $flow->id);
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
            in_array($value, ['active', 'enabled', '1', 'published'], true) => ChatbotFlowStatus::Active,
            in_array($value, ['paused', 'inactive', '0'], true) => ChatbotFlowStatus::Inactive,
            default => ChatbotFlowStatus::Draft,
        };
    }
}
