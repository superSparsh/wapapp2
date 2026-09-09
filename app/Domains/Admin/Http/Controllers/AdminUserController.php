<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'email', 'last_login_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        $query = Admin::query()->with('adminRole:id,name');
        AdminListQuery::applySearch($query, $parsed['q'], ['name', 'email']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'id' => 'id',
                'name' => 'name',
                'email' => 'email',
                'last_login_at' => 'last_login_at',
            ],
            'id',
        );

        return view('admin.admins.index', [
            'admins' => $query->paginate(20)->withQueryString(),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
                ['value' => 'email', 'label' => 'Email A–Z', 'direction' => 'asc'],
                ['value' => 'last_login_at', 'label' => 'Last login', 'direction' => 'desc'],
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.admins.create', [
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', $this->uniqueEmailRule()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'admin_role' => ['nullable', 'string', $this->roleExistsRule()],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Admin::query()->create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'admin_role_id' => $this->roleId($validated['admin_role'] ?? null),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.admins.index')->with('status', 'Admin created.');
    }

    public function edit(Admin $admin): View
    {
        return view('admin.admins.edit', [
            'admin' => $admin,
            'roles' => $this->roles(),
        ]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', $this->uniqueEmailRule($admin)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'admin_role' => ['nullable', 'string', $this->roleExistsRule()],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $admin->name = $validated['name'];
        $admin->email = strtolower($validated['email']);
        $admin->admin_role_id = $this->roleId($validated['admin_role'] ?? null);
        $admin->is_active = $request->boolean('is_active', $admin->is_active);

        if (! empty($validated['password'])) {
            $admin->password = $validated['password'];
        }

        $admin->save();

        return redirect()->route('admin.admins.index')->with('status', 'Admin updated.');
    }

    public function toggleStatus(Admin $admin): RedirectResponse
    {
        if ((int) $admin->id === (int) auth('admin')->id()) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        $admin->is_active = ! $admin->is_active;
        $admin->save();

        return back()->with('status', 'Admin '.($admin->is_active ? 'enabled' : 'disabled').'.');
    }

    /**
     * Central-connection lookups keep validation working while a tenant connection is active.
     */
    private function uniqueEmailRule(?Admin $existing = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
            $query = Admin::query()->where('email', strtolower((string) $value));
            if ($existing !== null) {
                $query->whereKeyNot($existing->id);
            }
            if ($query->exists()) {
                $fail('This email is already registered.');
            }
        };
    }

    private function roleExistsRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            if (AdminRole::query()->where('uuid', (string) $value)->doesntExist()) {
                $fail('The selected role does not exist.');
            }
        };
    }

    /**
     * @return Collection<int, AdminRole>
     */
    private function roles(): Collection
    {
        return AdminRole::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    private function roleId(?string $uuid): ?int
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        $role = AdminRole::query()->where('uuid', $uuid)->first(['id']);

        return $role === null ? null : (int) $role->id;
    }
}
