<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\Tenant;
use App\Models\TriggerVariable;

final class TriggerVariableImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'trigger_templates';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('trigger_variables')) {
            $report->warn('Legacy table [trigger_variables] not found; skipped.');

            return;
        }

        $query = $this->legacy->db()->table('trigger_variables');
        if ($this->legacy->hasColumn('trigger_variables', 'customer_id')) {
            $query->where('customer_id', $customer->id);
        }

        foreach ($query->orderBy('id')->get() as $row) {
            $legacyId = (int) $row->id;
            $variableName = trim((string) ($row->variable_name ?? $row->name ?? ''));
            if ($variableName === '') {
                $report->bump($this->key(), 'skipped');

                continue;
            }

            $templateCode = (string) ($row->template_code ?? $row->template_name ?? $row->wa_template ?? '');
            $templateName = (string) ($row->template_name ?? $templateCode);
            $listId = null;
            $listName = (string) ($row->list_name ?? '');
            foreach (['mail_list_id', 'list_id'] as $column) {
                if (isset($row->{$column}) && filled($row->{$column})) {
                    $listId = $ids->getInt('list', (int) $row->{$column});
                    break;
                }
            }

            $lineId = null;
            foreach (['new_contact_id', 'whatsapp_line_id'] as $column) {
                if (isset($row->{$column}) && filled($row->{$column})) {
                    $lineId = $ids->getInt('line', (int) $row->{$column});
                    break;
                }
            }

            if ($dryRun) {
                $exists = TriggerVariable::query()->where('variable_name', $variableName)->exists();
                $report->bump($this->key(), $exists ? 'updated' : 'created');

                continue;
            }

            $existing = TriggerVariable::query()->where('variable_name', $variableName)->first();
            $attributes = [
                'variable_name' => $variableName,
                'template_code' => $templateCode !== '' ? $templateCode : $variableName,
                'template_name' => $templateName !== '' ? $templateName : $variableName,
                'whatsapp_line_id' => $lineId,
                'list_id' => $listId,
                'list_name' => $listName !== '' ? $listName : null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $trigger = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $trigger = TriggerVariable::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('trigger_variable', $legacyId, $trigger->id);
        }
    }
}
