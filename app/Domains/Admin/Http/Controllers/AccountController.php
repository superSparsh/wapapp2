<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function profile(): View
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        return view('admin.account.profile', [
            'admin' => $admin,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'max:191',
                function (string $attribute, mixed $value, \Closure $fail) use ($admin): void {
                    $exists = Admin::query()
                        ->where('email', strtolower((string) $value))
                        ->whereKeyNot($admin->id)
                        ->exists();
                    if ($exists) {
                        $fail('This email is already registered.');
                    }
                },
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $admin->name = $validated['name'];
        $admin->email = strtolower($validated['email']);

        if (! empty($validated['password'])) {
            $admin->password = $validated['password'];
        }

        $admin->save();

        return redirect()
            ->route('admin.account.profile')
            ->with('status', 'Profile updated.');
    }
}
