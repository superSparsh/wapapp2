<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\AdminImpersonationService;
use App\Domains\Admin\Services\CustomerAdminService;
use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerAdminService $customers,
        private readonly AdminImpersonationService $impersonation,
    ) {}

    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['created_at', 'name', 'company_name', 'email', 'status', 'id'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $filters = [
            'q' => $parsed['q'],
            'status' => (string) $request->query('status', ''),
            'date_from' => $parsed['date_from'],
            'date_to' => $parsed['date_to'],
            'sort' => $parsed['sort'],
            'direction' => $parsed['direction'],
        ];

        return view('admin.customers.index', [
            'customers' => $this->customers->paginate($filters),
            'filters' => $filters,
            'statuses' => TenantStatus::cases(),
            'sortOptions' => [
                ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
                ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
                ['value' => 'company_name', 'label' => 'Company A–Z', 'direction' => 'asc'],
                ['value' => 'email', 'label' => 'Email A–Z', 'direction' => 'asc'],
                ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
            ],
        ]);
    }

    public function show(Tenant $tenant): View
    {
        return view('admin.customers.show', $this->customers->detail($tenant));
    }

    public function edit(Tenant $tenant): View
    {
        return view('admin.customers.edit', $this->customers->detail($tenant));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'plan' => PublicId::uuidExistsRules(Plan::class),
            'status' => ['required', 'in:active,suspended,pending'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $plan = PublicId::find(Plan::class, $validated['plan'] ?? null);
        unset($validated['plan']);
        $validated['plan_id'] = $plan?->id;

        $this->customers->update($tenant, $validated);

        return redirect()
            ->route('admin.customers.show', $tenant)
            ->with('status', 'Customer updated.');
    }

    public function toggleStatus(Tenant $tenant): RedirectResponse
    {
        $next = $tenant->status === TenantStatus::Active
            ? TenantStatus::Suspended
            : TenantStatus::Active;

        $this->customers->setStatus($tenant, $next);

        return back()->with('status', 'Customer marked as '.$next->value.'.');
    }

    public function assignPlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => PublicId::uuidExistsRules(Plan::class),
        ]);

        $plan = PublicId::find(Plan::class, $validated['plan'] ?? null);
        $this->customers->assignPlan($tenant, $plan?->id);

        return back()->with('status', 'Plan assigned.');
    }

    public function extendValidity(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $this->customers->extendValidity($tenant, (int) $validated['days']);

        return back()->with('status', 'Validity extended by '.$validated['days'].' day(s).');
    }

    public function loginAs(Tenant $tenant): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        try {
            $this->impersonation->loginAsTenant($admin, $tenant);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard')
            ->with('status', 'You are now logged in as '.$tenant->name.'.');
    }
}
