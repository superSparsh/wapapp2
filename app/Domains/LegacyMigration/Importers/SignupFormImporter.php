<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\FormBuilder\Enums\FormStatus;
use App\Domains\FormBuilder\Support\FormFieldNormalizer;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\SignupForm;
use App\Models\Tenant;

final class SignupFormImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'forms';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        $this->importFormsTable($customer, $ids, $report, $dryRun);
        $this->importFormBuilderTable($customer, $ids, $report, $dryRun);
    }

    private function importFormsTable(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('forms')) {
            return;
        }

        $query = $this->legacy->db()->table('forms');
        if ($this->legacy->hasColumn('forms', 'customer_id')) {
            $query->where('customer_id', $customer->id);
        }

        foreach ($query->orderBy('id')->get() as $row) {
            $this->upsertForm($row, $ids, $report, $dryRun, 'forms');
        }
    }

    private function importFormBuilderTable(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('formbuilder')) {
            return;
        }

        $query = $this->legacy->db()->table('formbuilder');
        if ($this->legacy->hasColumn('formbuilder', 'customer_id')) {
            $query->where('customer_id', $customer->id);
        }

        foreach ($query->orderBy('id')->get() as $row) {
            $this->upsertForm($row, $ids, $report, $dryRun, 'formbuilder');
        }
    }

    private function upsertForm(
        object $row,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
        string $sourceTable,
    ): void {
        $legacyId = (int) $row->id;
        $name = trim((string) ($row->name ?? $row->form_name ?? $row->title ?? 'Untitled Form'));
        $listId = null;
        foreach (['mail_list_id', 'list_id'] as $column) {
            if (isset($row->{$column}) && filled($row->{$column})) {
                $listId = $ids->getInt('list', (int) $row->{$column});
                break;
            }
        }

        $templateId = null;
        if (isset($row->template_id) && filled($row->template_id)) {
            $templateId = $ids->getInt('template', (int) $row->template_id);
        }

        $lineId = null;
        foreach (['new_contact_id', 'whatsapp_line_id'] as $column) {
            if (isset($row->{$column}) && filled($row->{$column})) {
                $lineId = $ids->getInt('line', (int) $row->{$column});
                break;
            }
        }

        $active = true;
        if (isset($row->activate)) {
            $active = (bool) $row->activate;
        } elseif (isset($row->active)) {
            $active = (bool) $row->active;
        } elseif (isset($row->status)) {
            $statusRaw = strtolower((string) $row->status);
            $active = ! in_array($statusRaw, ['0', 'inactive', 'disabled', 'false'], true);
        }
        $fields = $this->parseFields($row);
        $mapKey = $sourceTable === 'formbuilder' ? 'formbuilder' : 'form';

        if ($dryRun) {
            $exists = SignupForm::query()->where('name', $name)->exists();
            $report->bump($this->key(), $exists ? 'updated' : 'created');

            return;
        }

        $existingId = $ids->getInt($mapKey, $legacyId);
        $existing = $existingId ? SignupForm::query()->find($existingId) : null;
        $existing ??= SignupForm::query()->where('name', $name)->first();

        $slug = $existing?->slug ?? SignupForm::generateSlug($name);
        $attributes = [
            'name' => $name,
            'slug' => $slug,
            'status' => FormStatus::fromBoolean($active),
            'whatsapp_line_id' => $lineId,
            'list_id' => $listId,
            'template_id' => $templateId,
            'team_member_name' => $row->team_member_name ?? null,
            'fields' => $fields,
            'logo_path' => $row->logo ?? $row->logo_path ?? null,
            'redirect_url' => $row->redirect_url ?? $row->redirect ?? null,
            'custom_css' => $row->custom_css ?? null,
            'embed_settings' => SignupForm::defaultEmbedSettings(),
            'submission_count' => (int) ($row->submission_count ?? $row->submissions ?? 0),
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();
            $form = $existing;
            $report->bump($this->key(), 'updated');
        } else {
            $form = SignupForm::query()->create($attributes);
            $form->forceFill(['embed_code' => $form->generateEmbedCode()])->save();
            $report->bump($this->key(), 'created');
        }

        $ids->put($mapKey, $legacyId, $form->id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseFields(object $row): array
    {
        foreach (['fields', 'form_fields', 'schema', 'payload'] as $column) {
            if (! isset($row->{$column}) || ! filled($row->{$column})) {
                continue;
            }

            $raw = $row->{$column};
            if (is_array($raw)) {
                return FormFieldNormalizer::normalizeList(array_values($raw));
            }

            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return FormFieldNormalizer::normalizeList(array_values($decoded));
                }
            }
        }

        return FormFieldNormalizer::normalizeList([
            ['type' => 'phone', 'label' => 'Phone', 'required' => true, 'placeholder' => 'Phone number'],
            ['type' => 'first_name', 'label' => 'First name', 'required' => true, 'placeholder' => 'First name'],
            ['type' => 'last_name', 'label' => 'Last name', 'required' => false, 'placeholder' => 'Last name'],
        ]);
    }
}
