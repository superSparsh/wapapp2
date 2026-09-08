<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.admins.index', [
            'admins' => Admin::query()->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.admins.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        Admin::query()->create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.admins.index')->with('status', 'Admin created.');
    }

    public function edit(Admin $admin): View
    {
        return view('admin.admins.edit', compact('admin'));
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191', Rule::unique('admins', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $admin->name = $validated['name'];
        $admin->email = strtolower($validated['email']);
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
}
