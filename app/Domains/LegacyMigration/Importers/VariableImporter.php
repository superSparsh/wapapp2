<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Domains\Templates\Enums\VariableDataType;
use App\Domains\Templates\Enums\VariableType;
use App\Models\Tenant;
use App\Models\Variable;

final class VariableImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'variables';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('variables')) {
            $report->warn('Legacy table [variables] not found; skipped.');

            return;
        }

        $query = $this->legacy->db()->table('variables');
        if ($this->legacy->hasColumn('variables', 'customer_id')) {
            $query->where('customer_id', $customer->id);
        }

        $rows = $query->orderBy('id')->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->name ?? $row->variable_name ?? ''));
            if ($name === '') {
                $report->bump($this->key(), 'skipped');

                continue;
            }

            $lineId = $this->resolveLineId($row, $ids);
            $dataType = $this->mapDataType($row->data_type ?? $row->type ?? 'string');
            $varType = $this->mapType($row->variable_type ?? $row->var_type ?? null);
            $value = isset($row->value) ? (string) $row->value : (isset($row->default_value) ? (string) $row->default_value : null);

            if ($dryRun) {
                $exists = Variable::query()->where('name', $name)->exists();
                $report->bump($this->key(), $exists ? 'updated' : 'created');

                continue;
            }

            $existing = Variable::query()->where('name', $name)->first();
            $attributes = [
                'type' => $varType,
                'name' => $name,
                'data_type' => $dataType,
                'value' => $value,
                'whatsapp_line_id' => $lineId,
                'team_member_name' => $row->team_member_name ?? null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $variable = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $variable = Variable::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('variable', $legacyId, $variable->id);
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

        return null;
    }

    private function mapDataType(mixed $raw): VariableDataType
    {
        $value = strtolower(trim((string) ($raw ?? 'string')));

        return match (true) {
            str_contains($value, 'num') || $value === 'integer' || $value === 'int' => VariableDataType::Number,
            str_contains($value, 'url') || str_contains($value, 'link') => VariableDataType::Url,
            str_contains($value, 'image') || str_contains($value, 'img') => VariableDataType::Image,
            str_contains($value, 'video') => VariableDataType::Video,
            str_contains($value, 'pdf') || str_contains($value, 'doc') => VariableDataType::Pdf,
            default => VariableDataType::String,
        };
    }

    private function mapType(mixed $raw): VariableType
    {
        $value = strtolower(trim((string) ($raw ?? 'dynamic')));

        return str_contains($value, 'static') ? VariableType::Static : VariableType::Dynamic;
    }
}
