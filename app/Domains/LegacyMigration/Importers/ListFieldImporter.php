<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\Audience\Models\ListField;
use App\Domains\Audience\Models\ListFieldOption;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Legacy list custom fields → list_fields + list_field_options.
 * Contact values are folded in ContactImporter via the list_field id map.
 */
final class ListFieldImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'list_fields';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('fields')) {
            return;
        }

        $listIds = $this->legacy->db()->table('mail_lists')
            ->where('customer_id', $customer->id)
            ->pluck('id');

        if ($listIds->isEmpty()) {
            return;
        }

        $fields = $this->legacy->db()->table('fields')
            ->whereIn('mail_list_id', $listIds)
            ->orderBy('id')
            ->get();

        foreach ($fields as $row) {
            $this->importField($row, $ids, $report, $dryRun);
        }
    }

    private function importField(object $row, MigrationIdMap $ids, MigrationReport $report, bool $dryRun): void
    {
        $legacyId = (int) $row->id;
        $mailListId = $ids->getInt('list', (int) $row->mail_list_id);
        if ($mailListId === null) {
            $report->bump($this->key(), 'skipped');

            return;
        }

        $tag = trim((string) ($row->tag ?? ''));
        $label = trim((string) ($row->label ?? $tag));
        if ($tag === '') {
            $tag = Str::upper(Str::slug($label !== '' ? $label : 'field_'.$legacyId, '_'));
        }

        if (in_array($tag, ListField::PROTECTED_TAGS, true)) {
            $report->bump($this->key(), 'skipped');

            return;
        }

        $type = $this->mapType((string) ($row->type ?? 'text'));
        $existingId = $ids->getInt('list_field', $legacyId);
        $existing = $existingId
            ? ListField::query()->find($existingId)
            : ListField::query()->where('mail_list_id', $mailListId)->where('tag', $tag)->first();

        if ($dryRun) {
            $report->bump($this->key(), $existing ? 'updated' : 'created');

            return;
        }

        $attributes = [
            'mail_list_id' => $mailListId,
            'label' => $label !== '' ? $label : $tag,
            'type' => $type,
            'tag' => $tag,
            'default_value' => filled($row->default_value ?? null) ? (string) $row->default_value : null,
            'required' => (bool) ($row->required ?? false),
            'visible' => ! isset($row->visible) || (bool) $row->visible,
            'sort_order' => (int) ($row->custom_order ?? $row->id ?? 0),
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();
            $field = $existing;
            $report->bump($this->key(), 'updated');
        } else {
            $field = ListField::query()->create($attributes);
            $report->bump($this->key(), 'created');
        }

        $ids->put('list_field', $legacyId, $field->id);
        $ids->put('list_field_tag', $legacyId, $field->tag);

        $this->importOptions($legacyId, (int) $field->id, $report, $dryRun);
    }

    private function importOptions(int $legacyFieldId, int $fieldId, MigrationReport $report, bool $dryRun): void
    {
        if (! $this->legacy->tableExists('field_options')) {
            return;
        }

        $options = $this->legacy->db()->table('field_options')
            ->where('field_id', $legacyFieldId)
            ->orderBy('id')
            ->get();

        $sort = 0;
        foreach ($options as $option) {
            $label = trim((string) ($option->label ?? ''));
            $value = trim((string) ($option->value ?? $label));
            if ($label === '' && $value === '') {
                continue;
            }

            if ($dryRun) {
                $report->bump('list_field_options', 'created');

                continue;
            }

            ListFieldOption::query()->updateOrCreate(
                [
                    'list_field_id' => $fieldId,
                    'value' => $value !== '' ? $value : $label,
                ],
                [
                    'label' => $label !== '' ? $label : $value,
                    'sort_order' => $sort++,
                ],
            );
            $report->bump('list_field_options', 'updated');
        }
    }

    private function mapType(string $type): string
    {
        $value = strtolower(trim($type));

        return in_array($value, ListField::TYPES, true) ? $value : ListField::TYPE_TEXT;
    }
}
