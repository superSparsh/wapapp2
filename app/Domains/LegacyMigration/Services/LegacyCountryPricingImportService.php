<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Models\CountryPricing;
use App\Models\CountryPricingLog;

class LegacyCountryPricingImportService
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, logs_imported: int}
     */
    public function import(bool $dryRun = false, bool $withLogs = false): array
    {
        $this->legacy->assertReady();

        if (! $this->legacy->tableExists('country_pricing')) {
            throw new \RuntimeException('Legacy table [country_pricing] was not found.');
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'logs_imported' => 0];

        $rows = $this->legacy->db()->table('country_pricing')->orderBy('id')->get();

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row->country_code ?? '')));
            if ($code === '') {
                $stats['skipped']++;

                continue;
            }

            $payload = [
                'country_name' => (string) ($row->country_name ?? $code),
                'country_code' => $code,
                'dial_code' => $row->dial_code ?? null,
                'currency' => (string) ($row->currency ?? 'USD'),
                'marketing_price' => $this->nullableFloat($row->marketing_price ?? null),
                'utility_price' => $this->nullableFloat($row->utility_price ?? null),
                'auth_price' => $this->nullableFloat($row->auth_price ?? null),
                'auth_international_price' => $this->nullableFloat($row->auth_international_price ?? null),
                'service_price' => $this->nullableFloat($row->service_price ?? null),
                'tekpro_marketing_price' => $this->nullableFloat($row->tekpro_marketing_price ?? null),
                'tekpro_utility_price' => $this->nullableFloat($row->tekpro_utility_price ?? null),
                'tekpro_auth_price' => $this->nullableFloat($row->tekpro_auth_price ?? null),
                'tekpro_auth_international_price' => $this->nullableFloat($row->tekpro_auth_international_price ?? null),
                'tekpro_service_price' => $this->nullableFloat($row->tekpro_service_price ?? null),
                'status' => (int) ($row->status ?? 1) === 1 ? 1 : 0,
            ];

            $existing = CountryPricing::query()->where('country_code', $code)->first();

            if ($dryRun) {
                $stats[$existing ? 'updated' : 'created']++;

                continue;
            }

            if ($existing) {
                $existing->fill($payload)->save();
                $stats['updated']++;
            } else {
                CountryPricing::query()->create($payload);
                $stats['created']++;
            }
        }

        if ($withLogs && $this->legacy->tableExists('country_pricing_logs')) {
            $stats['logs_imported'] = $this->importLogs($dryRun);
        }

        return $stats;
    }

    private function importLogs(bool $dryRun): int
    {
        $count = 0;
        $rows = $this->legacy->db()->table('country_pricing_logs')->orderBy('id')->get();

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row->country_code ?? '')));
            if ($code === '') {
                continue;
            }

            if ($dryRun) {
                $count++;

                continue;
            }

            CountryPricingLog::query()->create([
                'country_code' => $code,
                'conversation' => (string) ($row->conversation ?? 'marketing'),
                'old_price' => $this->nullableFloat($row->old_price ?? null),
                'new_price' => $this->nullableFloat($row->new_price ?? null),
                'updated_by' => (int) ($row->updated_by ?? 0),
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
            $count++;
        }

        return $count;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }
}
