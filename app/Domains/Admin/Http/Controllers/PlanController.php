<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['sort_order', 'name', 'price', 'id'],
            defaultSort: 'sort_order',
            defaultDirection: 'asc',
        );

        $query = Plan::query()->withCount('tenants');
        AdminListQuery::applySearch($query, $parsed['q'], ['name', 'slug']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'sort_order' => 'sort_order',
                'name' => 'name',
                'price' => 'price',
                'id' => 'id',
            ],
            'sort_order',
        );

        return view('admin.plans.index', [
            'plans' => $query->paginate(20)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'sort_order', 'label' => 'Sort order', 'direction' => 'asc'],
                ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
                ['value' => 'price', 'label' => 'Price low–high', 'direction' => 'asc'],
                ['value' => 'price', 'label' => 'Price high–low', 'direction' => 'desc'],
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        Plan::query()->create($validated);

        return redirect()->route('admin.plans.index')->with('status', 'Plan created.');
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('admin.plans.index')->with('status', 'Plan updated.');
    }

    public function toggleStatus(Plan $plan): RedirectResponse
    {
        $plan->is_active = ! $plan->is_active;
        $plan->save();

        return back()->with('status', 'Plan '.($plan->is_active ? 'activated' : 'deactivated').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => [
                'required',
                'string',
                'max:191',
                function (string $attribute, mixed $value, \Closure $fail) use ($plan): void {
                    $query = Plan::query()->where('slug', (string) $value);
                    if ($plan !== null) {
                        $query->whereKeyNot($plan->id);
                    }
                    if ($query->exists()) {
                        $fail('The slug has already been taken.');
                    }
                },
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_cycle' => ['required', 'string', 'max:32'],
            'messages_limit' => ['nullable', 'integer', 'min:0'],
            'contacts_limit' => ['nullable', 'integer', 'min:0'],
            'team_members_limit' => ['nullable', 'integer', 'min:0'],
            'whatsapp_lines_limit' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', $plan?->is_active ?? true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
