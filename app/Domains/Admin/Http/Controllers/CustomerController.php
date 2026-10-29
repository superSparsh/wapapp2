<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Account\Services\BusinessCustomTermsService;
use App\Domains\Admin\Services\AdminImpersonationService;
use App\Domains\Admin\Services\CustomerAdminService;
use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BusinessCustomTerm;
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
        private readonly BusinessCustomTermsService $customTerms,
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

    public function approveCustomTerms(Request $request, Tenant $tenant): RedirectResponse
    {
        $term = $this->requireCustomTerms($tenant);
        $admin = $this->currentAdmin();
        $note = $request->string('admin_note')->toString() ?: null;

        $this->customTerms->approve($term, $admin, $note);

        return back()->with('status', 'Custom terms approved.');
    }

    public function rejectCustomTerms(Request $request, Tenant $tenant): RedirectResponse
    {
        $term = $this->requireCustomTerms($tenant);
        $admin = $this->currentAdmin();
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
            'suspend' => ['nullable', 'boolean'],
        ]);

        $this->customTerms->reject(
            $term,
            $admin,
            $validated['admin_note'],
            $request->boolean('suspend'),
        );

        return back()->with('status', 'Custom terms rejected'.($request->boolean('suspend') ? ' and account suspended' : '').'.');
    }

    public function removeCustomTerms(Request $request, Tenant $tenant): RedirectResponse
    {
        $term = $this->requireCustomTerms($tenant);
        $admin = $this->currentAdmin();
        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'max:2000'],
            'suspend' => ['nullable', 'boolean'],
        ]);

        $this->customTerms->remove(
            $term,
            $admin,
            $validated['admin_note'],
            $request->boolean('suspend'),
        );

        return back()->with('status', 'Custom terms removed'.($request->boolean('suspend') ? ' and account suspended' : '').'.');
    }

    private function requireCustomTerms(Tenant $tenant): BusinessCustomTerm
    {
        $term = BusinessCustomTerm::query()->where('tenant_id', $tenant->id)->first();
        abort_unless($term instanceof BusinessCustomTerm, 404);

        return $term;
    }

    private function currentAdmin(): Admin
    {
        $admin = Auth::guard('admin')->user();
        abort_unless($admin instanceof Admin, 403);

        return $admin;
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
            'days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'wallet_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $days = (int) ($validated['days'] ?? 0);
        $walletAmount = round((float) ($validated['wallet_amount'] ?? 0), 2);

        if ($days < 1 && $walletAmount <= 0) {
            return back()->with('error', 'Enter days to extend and/or a wallet top-up amount.');
        }

        if ($days >= 1) {
            $this->customers->extendValidity($tenant, $days);
        }

        if ($walletAmount > 0) {
            $admin = $request->user('admin');
            $this->customers->creditWallet(
                $tenant,
                $walletAmount,
                'Admin wallet top-up for '.$tenant->id,
                adminName: $admin?->name,
                adminEmail: $admin?->email,
                adminId: $admin !== null ? (int) $admin->id : null,
            );
        }

        $parts = [];
        if ($days >= 1) {
            $parts[] = 'validity +'.$days.' day(s)';
        }
        if ($walletAmount > 0) {
            $parts[] = 'wallet +₹'.number_format($walletAmount, 2);
        }

        return back()->with('status', 'Updated: '.implode(', ', $parts).'.');
    }

    public function activityLogs(Request $request, Tenant $tenant): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['created_at', 'action', 'scope'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        return view(
            'admin.customers.activity-logs',
            $this->customers->activityLogs(
                $tenant,
                $request->query('scope'),
                sort: $parsed['sort'],
                direction: $parsed['direction'],
            ),
        );
    }

    public function updateSettings(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'inbox_phone_masking_enabled' => ['nullable', 'boolean'],
        ]);

        $this->customers->updateInboxSettings(
            $tenant,
            $request->boolean('inbox_phone_masking_enabled'),
        );

        return back()->with('status', 'Customer inbox settings updated.');
    }

    public function setWalletDisplayCurrency(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'in:USD,INR,usd,inr'],
        ]);

        $this->customers->setWalletDisplayCurrency($tenant, (string) $validated['currency']);

        return back()->with('status', 'Wallet display currency set to '.strtoupper($validated['currency']).'.');
    }

    public function debitWallet(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'wallet_amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = round((float) $validated['wallet_amount'], 2);
        $admin = $request->user('admin');
        $description = trim((string) ($validated['description'] ?? ''));
        if ($description === '') {
            $description = 'Admin wallet deduction for '.$tenant->id;
        }

        try {
            $this->customers->debitWallet(
                $tenant,
                $amount,
                $description,
                adminName: $admin?->name,
                adminEmail: $admin?->email,
                adminId: $admin !== null ? (int) $admin->id : null,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Wallet deducted ₹'.number_format($amount, 2).'.');
    }

    public function deleteWalletTransaction(Request $request, Tenant $tenant, string $transaction): RedirectResponse
    {
        try {
            $this->customers->deleteWalletTransaction($tenant, $transaction);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Wallet activity deleted and balance adjusted.');
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

        return redirect()->route('dashboard');
    }

    public function wipeAccount(Request $request, Tenant $tenant): RedirectResponse
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $this->customers->wipeAccountData(
            $tenant,
            $admin?->name ?? $admin?->email ?? 'admin',
        );

        return redirect()
            ->route('admin.customers.index')
            ->with('status', 'Customer account data wiped. Profile and subscription were kept for restore.');
    }
}
