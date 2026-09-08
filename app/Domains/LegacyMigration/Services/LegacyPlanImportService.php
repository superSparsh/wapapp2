<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Enums\BillingCycle;
use App\Models\Plan;
use Illuminate\Support\Str;

class LegacyPlanImportService
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(bool $dryRun = false): array
    {
        $this->legacy->assertReady();

        if (! $this->legacy->tableExists('plans')) {
            throw new \RuntimeException('Legacy table [plans] was not found.');
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $rows = $this->legacy->db()->table('plans')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $name = trim((string) ($row->name ?? ''));
            if ($name === '') {
                $stats['skipped']++;

                continue;
            }

            $legacyId = (int) $row->id;
            $baseSlug = Str::slug($name) ?: 'plan-'.$legacyId;

            $existing = Plan::query()
                ->get()
                ->first(fn (Plan $plan) => (int) data_get($plan->features, 'legacy_plan_id') === $legacyId);

            $slug = $existing?->slug ?? $this->allocateSlug($baseSlug, $legacyId);

            $payload = [
                'name' => $name,
                'slug' => $slug,
                'description' => $row->description ?? null,
                'price' => (float) ($row->price ?? 0),
                'currency' => 'INR',
                'billing_cycle' => $this->mapBillingCycle($row),
                'sort_order' => $legacyId,
                'is_active' => in_array(strtolower((string) ($row->status ?? '')), ['active', '1', 'yes'], true),
                'features' => [
                    'legacy_plan_id' => $legacyId,
                    'legacy_uid' => $row->uid ?? null,
                    'legacy_options' => $this->decodeOptions($row->options ?? null),
                    'legacy_credit_option' => $row->credit_option ?? null,
                    'ai_response' => (bool) ($row->ai_response ?? false),
                    'is_international_plan' => (bool) ($row->is_international_plan ?? false),
                ],
            ];

            if ($dryRun) {
                $stats[$existing ? 'updated' : 'created']++;

                continue;
            }

            if ($existing !== null) {
                $existing->forceFill($payload)->save();
                $stats['updated']++;
            } else {
                Plan::query()->create($payload);
                $stats['created']++;
            }
        }

        return $stats;
    }

    public function ensureDefaultPlan(): Plan
    {
        $plan = Plan::query()->where('is_active', true)->orderBy('sort_order')->first();

        if ($plan !== null) {
            return $plan;
        }

        return Plan::query()->create([
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'Default plan for migrated tenants',
            'price' => 0,
            'currency' => 'INR',
            'billing_cycle' => BillingCycle::Monthly,
            'messages_limit' => 50000,
            'contacts_limit' => 10000,
            'team_members_limit' => 10,
            'whatsapp_lines_limit' => 5,
            'sort_order' => 1,
            'is_active' => true,
            'features' => ['source' => 'auto_seed'],
        ]);
    }

    private function allocateSlug(string $base, int $legacyId): string
    {
        $slug = $base;
        $i = 2;

        while (Plan::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$legacyId;
            if (! Plan::query()->where('slug', $slug)->exists()) {
                break;
            }
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function mapBillingCycle(object $row): BillingCycle
    {
        $unit = strtolower((string) ($row->frequency_unit ?? 'month'));

        return match (true) {
            str_contains($unit, 'year') => BillingCycle::Yearly,
            str_contains($unit, 'quarter') => BillingCycle::Quarterly,
            default => BillingCycle::Monthly,
        };
    }

    private function decodeOptions(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }
}
