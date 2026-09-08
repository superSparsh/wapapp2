<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\InteractiveMessage;
use App\Models\Tenant;

final class InteractiveMessageImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'interactive_messages';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('interactive_messages')) {
            $report->warn('Legacy table [interactive_messages] not found; skipped free templates.');

            return;
        }

        $query = $this->legacy->db()->table('interactive_messages');
        if ($this->legacy->hasColumn('interactive_messages', 'customer_id')) {
            $query->where('customer_id', $customer->id);
        }

        $rows = $query->orderBy('id')->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->name ?? $row->title ?? 'Untitled Message'));
            $type = $this->mapType($row->type ?? $row->message_type ?? 'button');
            $lineId = $this->resolveLineId($row, $ids);
            $content = $this->buildContent($row);

            if ($dryRun) {
                $exists = InteractiveMessage::query()
                    ->where('name', $name)
                    ->when($lineId, fn ($q) => $q->where('whatsapp_line_id', $lineId))
                    ->exists();
                $report->bump($this->key(), $exists ? 'updated' : 'created');

                continue;
            }

            $existing = InteractiveMessage::query()
                ->where('name', $name)
                ->when($lineId !== null, fn ($q) => $q->where('whatsapp_line_id', $lineId))
                ->first();

            $attributes = [
                'name' => $name,
                'type' => $type,
                'content' => $content,
                'whatsapp_line_id' => $lineId,
                'team_member_name' => $row->team_member_name ?? null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $message = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $message = InteractiveMessage::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('interactive_message', $legacyId, $message->id);
        }
    }

    private function resolveLineId(object $row, MigrationIdMap $ids): ?int
    {
        foreach (['new_contact_id', 'whatsapp_line_id', 'contact_id'] as $column) {
            if (isset($row->{$column}) && filled($row->{$column})) {
                $mapped = $ids->getInt('line', (int) $row->{$column});
                if ($mapped !== null) {
                    return $mapped;
                }
            }
        }

        $lines = $ids->all()['line'] ?? [];

        return count($lines) === 1 ? (int) reset($lines) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContent(object $row): array
    {
        if (isset($row->content) && is_string($row->content) && $row->content !== '') {
            $decoded = json_decode($row->content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (isset($row->payload) && is_string($row->payload) && $row->payload !== '') {
            $decoded = json_decode($row->payload, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $buttons = [];
        if (isset($row->buttons) && is_string($row->buttons) && $row->buttons !== '') {
            $decoded = json_decode($row->buttons, true);
            if (is_array($decoded)) {
                $buttons = array_values($decoded);
            }
        }

        $headerType = strtolower((string) ($row->header_type ?? 'none'));
        if (! in_array($headerType, ['none', 'text', 'image', 'video', 'document'], true)) {
            $headerType = 'none';
        }

        return [
            'body' => (string) ($row->body ?? $row->message ?? ''),
            'footer' => (string) ($row->footer ?? $row->footer_text ?? ''),
            'buttons' => $buttons,
            'header' => [
                'type' => $headerType,
                'text' => (string) ($row->header_text ?? $row->header_desc ?? ''),
                'media_path' => $row->header_media ?? null,
            ],
            'list_button_text' => (string) ($row->list_button_text ?? 'Options'),
            'list_sections' => [],
            'catalog_id' => (string) ($row->catalog_id ?? ''),
            'product_retailer_id' => (string) ($row->product_retailer_id ?? ''),
            'flow_id' => (string) ($row->flow_id ?? ''),
            'flow_cta' => (string) ($row->flow_cta ?? ''),
            'legacy_id' => (int) ($row->id ?? 0),
        ];
    }

    private function mapType(mixed $raw): string
    {
        $value = strtolower(trim((string) ($raw ?? 'button')));

        return match (true) {
            str_contains($value, 'list') => 'list',
            str_contains($value, 'product') || str_contains($value, 'catalog') => 'product',
            str_contains($value, 'flow') => 'flow',
            default => 'button',
        };
    }
}
