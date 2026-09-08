<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\RecordStatus;
use App\Models\MailList;
use App\Models\Tenant;

final class MailListImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'lists';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('mail_lists')) {
            return;
        }

        $rows = $this->legacy->db()->table('mail_lists')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $name = trim((string) ($row->name ?? 'Untitled List'));
            $legacyId = (int) $row->id;

            $existingId = $ids->getInt('list', $legacyId);
            $existing = $existingId
                ? MailList::query()->find($existingId)
                : MailList::query()->where('name', $name)->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            if ($existing !== null) {
                $existing->forceFill([
                    'name' => $name,
                    'description' => $row->default_subject ?? $existing->description,
                    'status' => RecordStatus::Active,
                ])->save();
                $list = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $list = MailList::query()->create([
                    'name' => $name,
                    'description' => $row->default_subject ?? null,
                    'status' => RecordStatus::Active,
                ]);
                $report->bump($this->key(), 'created');
            }

            $ids->put('list', $legacyId, $list->id);
        }
    }
}
