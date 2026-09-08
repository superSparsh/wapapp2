<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\TenantStatus;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Tenant;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $tenants = Tenant::query();

        return [
            'customers_total' => (clone $tenants)->count(),
            'customers_active' => (clone $tenants)->where('status', TenantStatus::Active)->count(),
            'customers_suspended' => (clone $tenants)->where('status', TenantStatus::Suspended)->count(),
            'customers_pending' => (clone $tenants)->where('status', TenantStatus::Pending)->count(),
            'plans_active' => Plan::query()->where('is_active', true)->count(),
            'admins_active' => Admin::query()->where('is_active', true)->count(),
            'recent_customers' => Tenant::query()
                ->with('plan:id,name')
                ->latest('created_at')
                ->limit(8)
                ->get(),
            'plan_distribution' => Plan::query()
                ->withCount('tenants')
                ->orderByDesc('tenants_count')
                ->limit(6)
                ->get(['id', 'name', 'tenants_count']),
        ];
    }
}
