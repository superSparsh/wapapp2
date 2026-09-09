<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminRoleController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['name', 'id'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $query = AdminRole::query()->withCount('admins');
        AdminListQuery::applySearch($query, $parsed['q'], ['name', 'slug']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            ['name' => 'name', 'id' => 'id'],
            'name',
        );

        return view('admin.admin-roles.index', [
            'roles' => $query->paginate(20)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.admin-roles.form', [
            'permissions' => AdminRole::PERMISSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AdminRole::query()->create($this->validated($request));

        return redirect()->route('admin.admin-roles.index')->with('status', 'Role created.');
    }

    public function edit(AdminRole $role): View
    {
        return view('admin.admin-roles.form', [
            'role' => $role,
            'permissions' => AdminRole::PERMISSIONS,
        ]);
    }

    public function update(Request $request, AdminRole $role): RedirectResponse
    {
        $role->update($this->validated($request, $role));

        return redirect()->route('admin.admin-roles.index')->with('status', 'Role updated.');
    }

    public function destroy(AdminRole $role): RedirectResponse
    {
        if ($role->admins()->exists()) {
            return back()->with('error', 'Detach the admins on this role first.');
        }

        $role->delete();

        return back()->with('status', 'Role deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AdminRole $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    $query = AdminRole::query()->where('slug', Str::slug((string) $value));
                    if ($existing !== null) {
                        $query->whereKeyNot($existing->id);
                    }
                    if ($query->exists()) {
                        $fail('This slug is already taken.');
                    }
                },
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(AdminRole::PERMISSIONS)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = Str::slug(($data['slug'] ?? '') !== '' ? (string) $data['slug'] : $data['name']);
        $data['permissions'] = array_values($data['permissions'] ?? []);
        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? true);

        return $data;
    }
}
