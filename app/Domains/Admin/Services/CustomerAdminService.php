<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantUserAccess;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Models\WhatsappLine;
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

        $paginator = $query->paginate($perPage)->withQueryString();
        $wallets = $this->walletBalancesForTenants($paginator->getCollection());

        $paginator->getCollection()->transform(function (Tenant $tenant) use ($wallets): Tenant {
            $info = $wallets[(string) $tenant->id] ?? ['balance' => null, 'currency' => 'INR'];
            $tenant->setAttribute('wallet_balance', $info['balance']);
            $tenant->setAttribute('wallet_currency', $info['currency']);

            return $tenant;
        });

        return $paginator;
    }

    /**
     * Lightweight per-tenant wallet snapshot for the customers index table.
     *
     * @param  \Illuminate\Support\Collection<int, Tenant>  $tenants
     * @return array<string, array{balance: float|null, currency: string}>
     */
    public function walletBalancesForTenants($tenants): array
    {
        $balances = [];

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        try {
            foreach ($tenants as $tenant) {
                $tenantId = (string) $tenant->id;
                $balances[$tenantId] = ['balance' => null, 'currency' => 'INR'];

                try {
                    tenancy()->initialize($tenant);
                    $wallet = WalletAccount::query()->first();
                    $balances[$tenantId] = [
                        'balance' => $wallet !== null ? (float) $wallet->balance : null,
                        'currency' => strtoupper((string) ($wallet?->currency ?: 'INR')),
                    ];
                } catch (\Throwable) {
                    // Tenant DB missing / unreachable - leave balance null.
                } finally {
                    if (tenancy()->initialized) {
                        tenancy()->end();
                    }
                }
            }
        } finally {
            if ($wasInitialized && $previous) {
                tenancy()->initialize($previous);
            }
        }

        return $balances;
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
        $validUntil = $this->parseValidUntil($settings);
        $daysLeft = $validUntil === null
            ? null
            : (int) now()->startOfDay()->diffInDays($validUntil->copy()->startOfDay(), false);

        $ops = $this->operationalSnapshot($tenant);

        return [
            'tenant' => $tenant->loadMissing('plan'),
            'access_rows' => $accessRows,
            'owner_email' => $ownerEmail,
            'settings' => $settings,
            'valid_until' => $validUntil,
            'days_left' => $daysLeft,
            'wallet' => $ops['wallet'],
            'subscription' => $ops['subscription'],
            'lines' => $ops['lines'],
            'usage' => $ops['usage'],
            'recent_wallet' => $ops['recent_wallet'],
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'price', 'currency']),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function parseValidUntil(array $settings): ?Carbon
    {
        $raw = $settings['valid_until'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Tenant-DB snapshot for the admin customer profile (safe no-op if DB missing).
     *
     * @return array{
     *   wallet: array{balance: float|null, currency: string},
     *   subscription: array{status: string|null, starts_at: string|null, ends_at: string|null, amount: string|null, currency: string|null},
     *   lines: list<array{phone: string, display_name: string, quality: string, tier: string, connected: bool, is_default: bool}>,
     *   usage: array{outbound_7d: int, inbound_7d: int, failed_7d: int, contacts: int, campaigns: int, templates: int, lines_total: int, lines_connected: int},
     *   recent_wallet: list<array{type: string, amount: string, description: string, created_at: string|null}>
     * }
     */
    private function operationalSnapshot(Tenant $tenant): array
    {
        $empty = [
            'wallet' => ['balance' => null, 'currency' => 'INR'],
            'subscription' => [
                'status' => null,
                'starts_at' => null,
                'ends_at' => null,
                'amount' => null,
                'currency' => null,
            ],
            'lines' => [],
            'usage' => [
                'outbound_7d' => 0,
                'inbound_7d' => 0,
                'failed_7d' => 0,
                'contacts' => 0,
                'campaigns' => 0,
                'templates' => 0,
                'lines_total' => 0,
                'lines_connected' => 0,
            ],
            'recent_wallet' => [],
        ];

        $wasInitialized = tenancy()->initialized;
        $previous = $wasInitialized ? tenant() : null;

        if ($wasInitialized) {
            tenancy()->end();
        }

        try {
            tenancy()->initialize($tenant);

            $wallet = WalletAccount::query()->first();
            $subscription = Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->latest('id')
                ->first()
                ?? Subscription::query()->latest('id')->first();

            $lines = WhatsappLine::query()
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get()
                ->map(fn (WhatsappLine $line): array => [
                    'phone' => $line->displayPhone(),
                    'display_name' => (string) ($line->display_name ?? ''),
                    'quality' => strtoupper((string) ($line->quality_rating ?: 'UNKNOWN')),
                    'tier' => (string) ($line->messaging_limit_tier ?: '-'),
                    'connected' => $line->isConnected(),
                    'is_default' => (bool) $line->is_default,
                ])
                ->all();

            $since = now()->subDays(7);

            $outbound = Message::query()
                ->where('direction', MessageDirection::Outbound)
                ->where('created_at', '>=', $since)
                ->count();
            $inbound = Message::query()
                ->where('direction', MessageDirection::Inbound)
                ->where('created_at', '>=', $since)
                ->count();
            $failed = Message::query()
                ->where('status', MessageStatus::Failed)
                ->where('created_at', '>=', $since)
                ->count();

            $recentWallet = WalletTransaction::query()
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (WalletTransaction $tx): array => [
                    'type' => $tx->type?->value ?? (string) $tx->type,
                    'amount' => number_format((float) $tx->amount, 2),
                    'description' => (string) ($tx->description ?: '-'),
                    'created_at' => $tx->created_at?->toDateTimeString(),
                ])
                ->all();

            return [
                'wallet' => [
                    'balance' => $wallet !== null ? (float) $wallet->balance : null,
                    'currency' => strtoupper((string) ($wallet?->currency ?: 'INR')),
                ],
                'subscription' => [
                    'status' => $subscription?->status?->value,
                    'starts_at' => $subscription?->starts_at?->toDateTimeString(),
                    'ends_at' => $subscription?->ends_at?->toDateTimeString(),
                    'amount' => $subscription?->amount !== null ? number_format((float) $subscription->amount, 2) : null,
                    'currency' => $subscription?->currency,
                ],
                'lines' => $lines,
                'usage' => [
                    'outbound_7d' => $outbound,
                    'inbound_7d' => $inbound,
                    'failed_7d' => $failed,
                    'contacts' => Contact::query()->count(),
                    'campaigns' => Campaign::query()->count(),
                    'templates' => Template::query()->count(),
                    'lines_total' => count($lines),
                    'lines_connected' => collect($lines)->where('connected', true)->count(),
                ],
                'recent_wallet' => $recentWallet,
            ];
        } catch (\Throwable) {
            return $empty;
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($wasInitialized && $previous) {
                tenancy()->initialize($previous);
            }
        }
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
    public function creditWallet(
        Tenant $tenant,
        float $amount,
        string $description = 'Admin wallet top-up',
        ?string $adminName = null,
        ?string $adminEmail = null,
        ?int $adminId = null,
    ): void {
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
            app(\App\Domains\Billing\Services\WalletService::class)->adminCredit(
                $amount,
                $description,
                [
                    'admin_id' => $adminId,
                    'admin_name' => $adminName,
                    'admin_email' => $adminEmail,
                ],
            );
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
            if ($wasInitialized && $previous) {
                tenancy()->initialize($previous);
            }
        }
    }

    /**
     * Wipe all operational tenant data; keep account + subscription for restore.
     *
     * @return array{tables_wiped: int, central_cleared: int}
     */
    public function wipeAccountData(Tenant $tenant, ?string $adminName = null): array
    {
        return app(TenantAccountWipeService::class)->wipe($tenant, $adminName);
    }

    /**
     * @return array{tenant: Tenant, logs: \Illuminate\Contracts\Pagination\LengthAwarePaginator, scopes: array<string, string>, activeScope: string, filters: array<string, string>, sortOptions: list<array{value: string, label: string, direction: string}>}
     */
    public function activityLogs(
        Tenant $tenant,
        ?string $scope = null,
        int $perPage = 25,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): array {
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
                $sort,
                $direction,
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
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
                'scope' => (string) ($scope ?? ''),
            ],
            'sortOptions' => [
                ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
                ['value' => 'action', 'label' => 'Action A–Z', 'direction' => 'asc'],
                ['value' => 'scope', 'label' => 'Scope', 'direction' => 'asc'],
            ],
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
