<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Services;

use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Enums\BillingCycle;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Str;

class LegacyPlanImportService
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, deactivated: int}
     */
    public function import(bool $dryRun = false, bool $deactivateNonLegacy = false): array
    {
        $this->legacy->assertReady();

        if (! $this->legacy->tableExists('plans')) {
            throw new \RuntimeException('Legacy table [plans] was not found.');
        }

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'deactivated' => 0];

        $rows = $this->legacy->db()->table('plans')
            ->orderBy('id')
            ->get();

        $importedLegacyIds = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->name ?? ''));
            if ($name === '') {
                $stats['skipped']++;

                continue;
            }

            $legacyId = (int) $row->id;
            $importedLegacyIds[] = $legacyId;
            $baseSlug = Str::slug($name) ?: 'plan-'.$legacyId;

            $existing = $this->findByLegacyPlanId($legacyId);

            $slug = $existing?->slug ?? $this->allocateSlug($baseSlug, $legacyId);
            $options = $this->decodeOptions($row->options ?? null);

            $payload = [
                'name' => $name,
                'slug' => $slug,
                'description' => $row->description ?? null,
                'price' => (float) ($row->price ?? 0),
                'currency' => $this->resolveCurrency($row),
                'billing_cycle' => $this->mapBillingCycle($row),
                'messages_limit' => $this->limitFromOptions($options, ['email_max', 'sending_quota', 'message_max']),
                'contacts_limit' => $this->limitFromOptions($options, ['subscriber_max', 'contact_max', 'list_max']),
                'team_members_limit' => $this->limitFromOptions($options, ['max_users', 'team_max', 'user_max']),
                'whatsapp_lines_limit' => $this->limitFromOptions($options, ['max_whatsapp_lines', 'whatsapp_lines', 'line_max', 'sending_servers_max']),
                'sort_order' => $legacyId,
                'is_active' => in_array(strtolower((string) ($row->status ?? '')), ['active', '1', 'yes'], true),
                'features' => [
                    'legacy_plan_id' => $legacyId,
                    'legacy_uid' => $row->uid ?? null,
                    'legacy_options' => $options,
                    'legacy_credit_option' => $row->credit_option ?? null,
                    'ai_response' => (bool) ($row->ai_response ?? false),
                    'is_international_plan' => (bool) ($row->is_international_plan ?? false),
                    'source' => 'legacy_import',
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

        if ($deactivateNonLegacy) {
            $stats['deactivated'] = $this->deactivateNonLegacyPlans($importedLegacyIds, $dryRun);
        }

        return $stats;
    }

    /**
     * Assign each migrated tenant the plan that matches their active legacy subscription.
     *
     * @return array{assigned: int, skipped: int, missing_plan: int, missing_subscription: int}
     */
    public function assignTenantPlans(bool $dryRun = false): array
    {
        $this->legacy->assertReady();

        $stats = ['assigned' => 0, 'skipped' => 0, 'missing_plan' => 0, 'missing_subscription' => 0];

        if (! $this->legacy->tableExists('subscriptions')) {
            throw new \RuntimeException('Legacy table [subscriptions] was not found.');
        }

        $planByLegacyId = Plan::query()
            ->get()
            ->filter(fn (Plan $plan) => filled(data_get($plan->features, 'legacy_plan_id')))
            ->keyBy(fn (Plan $plan) => (int) data_get($plan->features, 'legacy_plan_id'));

        $tenants = Tenant::query()->get()->filter(
            fn (Tenant $tenant) => filled(data_get($tenant->settings, 'legacy_customer_id'))
        );

        foreach ($tenants as $tenant) {
            $legacyCustomerId = (int) data_get($tenant->settings, 'legacy_customer_id');
            $legacyPlanId = $this->resolveActiveLegacyPlanId($legacyCustomerId);

            if ($legacyPlanId === null) {
                $stats['missing_subscription']++;

                continue;
            }

            $plan = $planByLegacyId->get($legacyPlanId);
            if (! $plan instanceof Plan) {
                $stats['missing_plan']++;

                continue;
            }

            if ((int) $tenant->plan_id === (int) $plan->id) {
                $stats['skipped']++;

                continue;
            }

            if (! $dryRun) {
                $tenant->forceFill(['plan_id' => $plan->id])->save();
            }

            $stats['assigned']++;
        }

        return $stats;
    }

    public function resolvePlanForLegacyCustomer(int $legacyCustomerId): ?Plan
    {
        $legacyPlanId = $this->resolveActiveLegacyPlanId($legacyCustomerId);
        if ($legacyPlanId === null) {
            return null;
        }

        return $this->findByLegacyPlanId($legacyPlanId);
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

    public function findByLegacyPlanId(int $legacyId): ?Plan
    {
        return Plan::query()
            ->get()
            ->first(fn (Plan $plan) => (int) data_get($plan->features, 'legacy_plan_id') === $legacyId);
    }

    public function resolveActiveLegacyPlanId(int $legacyCustomerId): ?int
    {
        if (! $this->legacy->tableExists('subscriptions')) {
            return null;
        }

        $query = $this->legacy->db()->table('subscriptions')
            ->where('customer_id', $legacyCustomerId)
            ->whereNotNull('plan_id');

        if ($this->legacy->hasColumn('subscriptions', 'status')) {
            $query->where(function ($builder): void {
                $builder->whereIn('status', ['active', 'Active', '1', 'paid', 'current'])
                    ->orWhereRaw('LOWER(status) = ?', ['active']);
            });
        }

        if ($this->legacy->hasColumn('subscriptions', 'updated_at')) {
            $query->orderByDesc('updated_at');
        } else {
            $query->orderByDesc('id');
        }

        $planId = $query->value('plan_id');

        return $planId !== null ? (int) $planId : null;
    }

    /**
     * @param  list<int>  $keepLegacyIds
     */
    private function deactivateNonLegacyPlans(array $keepLegacyIds, bool $dryRun): int
    {
        $count = 0;

        Plan::query()->get()->each(function (Plan $plan) use ($keepLegacyIds, $dryRun, &$count): void {
            $legacyId = data_get($plan->features, 'legacy_plan_id');
            $source = (string) data_get($plan->features, 'source', '');

            $isLegacy = $legacyId !== null && $legacyId !== '';
            $isDummy = in_array($source, ['auto_seed', 'seeder'], true)
                || ($plan->slug === 'professional' && ! $isLegacy);

            if ($isLegacy && in_array((int) $legacyId, $keepLegacyIds, true)) {
                return;
            }

            if (! $isDummy && $isLegacy) {
                return;
            }

            if (! $plan->is_active && ! $isDummy) {
                return;
            }

            // Deactivate non-legacy / seeder dummy plans so admin lists show legacy plans.
            if (! $isLegacy || $isDummy) {
                if (! $dryRun && $plan->is_active) {
                    $plan->forceFill(['is_active' => false])->save();
                }
                $count++;
            }
        });

        return $count;
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

    private function resolveCurrency(object $row): string
    {
        $code = strtoupper(trim((string) ($row->currency_code ?? '')));
        if (strlen($code) === 3) {
            return $code;
        }

        return 'INR';
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @param  list<string>  $keys
     */
    private function limitFromOptions(?array $options, array $keys): ?int
    {
        if ($options === null) {
            return null;
        }

        foreach ($keys as $key) {
            if (! array_key_exists($key, $options)) {
                continue;
            }

            $raw = $options[$key];
            if ($raw === null || $raw === '') {
                continue;
            }

            $value = (int) $raw;
            // Legacy uses -1 for unlimited.
            if ($value < 0) {
                return null;
            }

            return $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeOptions(mixed $raw): ?array
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
}
