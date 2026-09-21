<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class CustomerAdminService
{
    /**
     * @param  array{q?: string, status?: string, sort?: string, direction?: string, date_from?: string, date_to?: string}  $filters
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Tenant::query()->with('plan:id,name');

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder->where('id', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('company_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && TenantStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        AdminListQuery::applyDateRange(
            $query,
            'created_at',
            (string) ($filters['date_from'] ?? ''),
            (string) ($filters['date_to'] ?? ''),
        );

        AdminListQuery::applySort(
            $query,
            (string) ($filters['sort'] ?? 'created_at'),
            (string) ($filters['direction'] ?? 'desc'),
            [
                'created_at' => 'created_at',
                'name' => 'name',
                'company_name' => 'company_name',
                'email' => 'email',
                'status' => 'status',
                'id' => 'id',
            ],
            'created_at',
        );

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Tenant $tenant): array
    {
        $accessRows = TenantUserAccess::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        $ownerEmail = $accessRows->first()?->email;

        $settings = is_array($tenant->settings) ? $tenant->settings : [];

        return [
            'tenant' => $tenant->loadMissing('plan'),
            'access_rows' => $accessRows,
            'owner_email' => $ownerEmail,
            'settings' => $settings,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'price', 'currency']),
        ];
    }

    /**
     * @param  array{name?: string, company_name?: string, email?: string, phone?: string, plan_id?: int|null, status?: string, timezone?: string}  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $status = Arr::get($data, 'status');
        $attributes = [
            'name' => Arr::get($data, 'name', $tenant->name),
            'company_name' => Arr::get($data, 'company_name', $tenant->company_name),
            'email' => Arr::get($data, 'email', $tenant->email),
            'phone' => Arr::get($data, 'phone', $tenant->phone),
            'plan_id' => Arr::get($data, 'plan_id', $tenant->plan_id),
            'timezone' => Arr::get($data, 'timezone', $tenant->timezone),
        ];

        if (is_string($status) && TenantStatus::tryFrom($status) !== null) {
            $attributes['status'] = $status;
            $attributes['suspended_at'] = $status === TenantStatus::Suspended->value ? ($tenant->suspended_at ?? now()) : null;
        }

        $tenant->fill($attributes)->save();

        return $tenant->fresh(['plan']) ?? $tenant;
    }

    public function setStatus(Tenant $tenant, TenantStatus $status): Tenant
    {
        $tenant->status = $status;
        $tenant->suspended_at = $status === TenantStatus::Suspended ? now() : null;
        $tenant->save();

        return $tenant->fresh() ?? $tenant;
    }

    public function assignPlan(Tenant $tenant, ?int $planId): Tenant
    {
        if ($planId !== null && Plan::query()->whereKey($planId)->doesntExist()) {
            throw new \InvalidArgumentException('Plan not found.');
        }

        $tenant->plan_id = $planId;
        $tenant->save();

        return $tenant->fresh(['plan']) ?? $tenant;
    }

    public function extendValidity(Tenant $tenant, int $days): Tenant
    {
        if ($days < 1) {
            return $tenant;
        }

        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $current = isset($settings['valid_until'])
            ? Carbon::parse((string) $settings['valid_until'])
            : now();

        if ($current->isPast()) {
            $current = now();
        }

        $settings['valid_until'] = $current->addDays($days)->toDateString();
        $tenant->settings = $settings;
        $tenant->save();

        // Keep tenant DB subscription ends_at in sync so dashboard/profile stay consistent.
        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        try {
            tenancy()->initialize($tenant);
            $subscription = Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->latest('id')
                ->first();

            if ($subscription !== null) {
                $subscription->forceFill([
                    'ends_at' => Carbon::parse($settings['valid_until'])->endOfDay(),
                ])->save();
            }
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($wasInitialized && $previous) {
                tenancy()->initialize($previous);
            }
        }

        return $tenant->fresh() ?? $tenant;
    }

    /**
     * Credit tenant wallet from admin (initializes tenancy briefly).
     */
    public function creditWallet(Tenant $tenant, float $amount, string $description = 'Admin wallet top-up'): void
    {
        if ($amount <= 0) {
            return;
        }

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        try {
            tenancy()->initialize($tenant);
            app(\App\Domains\Billing\Services\WalletService::class)->adminCredit($amount, $description);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($previous !== null) {
                tenancy()->initialize($previous);
            }
        }
    }

    /**
     * @return array{tenant: Tenant, logs: \Illuminate\Contracts\Pagination\LengthAwarePaginator, scopes: array<string, string>, activeScope: string}
     */
    public function activityLogs(Tenant $tenant, ?string $scope = null, int $perPage = 25): array
    {
        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;
        $logs = null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        try {
            tenancy()->initialize($tenant);
            $logs = app(\App\Domains\Account\Services\ActivityLogService::class)->paginate(
                $scope !== null && $scope !== '' ? $scope : null,
                $perPage,
            );
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($previous !== null) {
                tenancy()->initialize($previous);
            }
        }

        if ($logs === null) {
            throw new \RuntimeException('Unable to load activity logs for this customer.');
        }

        return [
            'tenant' => $tenant,
            'logs' => $logs,
            'scopes' => (array) config('billing.activity_log.scopes', []),
            'activeScope' => (string) ($scope ?? ''),
        ];
    }

    public function updateInboxSettings(Tenant $tenant, bool $inboxPhoneMaskingEnabled): Tenant
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['inbox_phone_masking_enabled'] = $inboxPhoneMaskingEnabled;
        $tenant->settings = $settings;
        $tenant->save();

        return $tenant->fresh() ?? $tenant;
    }

    public function setWalletDisplayCurrency(Tenant $tenant, string $currency): Tenant
    {
        $normalized = strtoupper($currency);
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings['wallet_display_currency'] = $normalized;
        $tenant->settings = $settings;
        $tenant->save();

        return $tenant->fresh() ?? $tenant;
    }
}
