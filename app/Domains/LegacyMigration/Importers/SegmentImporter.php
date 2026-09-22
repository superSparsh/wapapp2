<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\Audience\Enums\SegmentConditionType;
use App\Domains\Audience\Models\ListField;
use App\Domains\Audience\Models\Segment;
use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Models\Tenant;

/**
 * Legacy segments + segment_conditions → segments.conditions JSON.
 */
final class SegmentImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'segments';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('segments')) {
            return;
        }

        $listIds = $this->legacy->db()->table('mail_lists')
            ->where('customer_id', $customer->id)
            ->pluck('id');

        if ($listIds->isEmpty()) {
            return;
        }

        $rows = $this->legacy->db()->table('segments')
            ->whereIn('mail_list_id', $listIds)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $this->importOne($row, $ids, $report, $dryRun);
        }
    }

    private function importOne(object $row, MigrationIdMap $ids, MigrationReport $report, bool $dryRun): void
    {
        $legacyId = (int) $row->id;
        $mailListId = $ids->getInt('list', (int) $row->mail_list_id);
        if ($mailListId === null) {
            $report->bump($this->key(), 'skipped');

            return;
        }

        $name = trim((string) ($row->name ?? 'Segment '.$legacyId));
        $match = strtolower((string) ($row->matching ?? 'all')) === 'any' ? 'any' : 'all';
        $rules = $this->buildRules($legacyId, $ids);

        $existingId = $ids->getInt('segment', $legacyId);
        $existing = $existingId
            ? Segment::query()->find($existingId)
            : Segment::query()->where('mail_list_id', $mailListId)->where('name', $name)->first();

        if ($dryRun) {
            $report->bump($this->key(), $existing ? 'updated' : 'created');

            return;
        }

        $attributes = [
            'name' => $name,
            'mail_list_id' => $mailListId,
            'conditions' => [
                'match' => $match,
                'rules' => $rules,
            ],
            'contact_count' => 0,
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();
            $segment = $existing;
            $report->bump($this->key(), 'updated');
        } else {
            $segment = Segment::query()->create($attributes);
            $report->bump($this->key(), 'created');
        }

        $ids->put('segment', $legacyId, $segment->id);
    }

    /**
     * @return list<array{field: string, type: string, value: mixed}>
     */
    private function buildRules(int $legacySegmentId, MigrationIdMap $ids): array
    {
        if (! $this->legacy->tableExists('segment_conditions')) {
            return [];
        }

        $conditions = $this->legacy->db()->table('segment_conditions')
            ->where('segment_id', $legacySegmentId)
            ->orderBy('id')
            ->get();

        $rules = [];
        foreach ($conditions as $condition) {
            $type = $this->mapOperator((string) ($condition->operator ?? 'equal'));
            if ($type === null) {
                continue;
            }

            $field = $this->resolveFieldKey($condition, $ids);
            if ($field === null) {
                continue;
            }

            $rules[] = [
                'field' => $field,
                'type' => $type->value,
                'value' => $condition->value ?? '',
            ];
        }

        return $rules;
    }

    private function resolveFieldKey(object $condition, MigrationIdMap $ids): ?string
    {
        $legacyFieldId = isset($condition->field_id) ? (int) $condition->field_id : 0;
        if ($legacyFieldId > 0) {
            $tag = $ids->get('list_field_tag', $legacyFieldId);
            if (is_string($tag) && $tag !== '') {
                return $tag;
            }

            $fieldId = $ids->getInt('list_field', $legacyFieldId);
            if ($fieldId !== null) {
                $tag = ListField::query()->where('id', $fieldId)->value('tag');
                if (is_string($tag) && $tag !== '') {
                    return $tag;
                }
            }
        }

        // Built-in subscriber columns used by some legacy segments.
        $operator = strtolower((string) ($condition->operator ?? ''));
        if (str_contains($operator, 'email')) {
            return 'email';
        }

        return null;
    }

    private function mapOperator(string $operator): ?SegmentConditionType
    {
        $value = strtolower(trim($operator));

        return match ($value) {
            'equal', 'equals', '=' => SegmentConditionType::Equals,
            'not_equal', 'not_equals', '!=' => SegmentConditionType::NotEquals,
            'contains' => SegmentConditionType::Contains,
            'starts', 'starts_with' => SegmentConditionType::StartsWith,
            'ends', 'ends_with' => SegmentConditionType::EndsWith,
            'greater', 'greater_than', '>' => SegmentConditionType::GreaterThan,
            'less', 'less_than', '<' => SegmentConditionType::LessThan,
            'blank', 'is_empty', 'empty' => SegmentConditionType::IsEmpty,
            'not_blank', 'is_not_empty', 'not_empty' => SegmentConditionType::IsNotEmpty,
            default => null,
        };
    }
}
