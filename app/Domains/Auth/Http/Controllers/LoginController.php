<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Account\Services\ActivityLogService;
use App\Domains\Admin\Support\MaintenanceLoginGate;
use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Http\Requests\LoginRequest;
use App\Domains\Auth\Services\LoginService;
use App\Domains\Team\Support\TeamPostLoginRedirect;
use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login', [
            'activeTab' => request('tab') === 'mobile' ? 1 : 0,
        ]);
    }

    public function store(LoginRequest $request, LoginService $loginService, ActivityLogService $activityLogService): RedirectResponse
    {
        try {
            $result = $loginService->attempt(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                remember: $request->boolean('remember'),
            );
        } catch (InvalidCredentialsException|AccountInactiveException $exception) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => $exception->getMessage()]);
        }

        if ($blocked = MaintenanceLoginGate::rejectUnlessCustomerAdmin($result->user, $result->guard)) {
            return $blocked->withInput($request->only('email', 'remember'));
        }

        $user = $result->user;
        $actorName = $user instanceof TeamMember
            ? $user->displayName()
            : (string) ($user instanceof User ? ($user->name ?? $user->email) : '');

        $activityLogService->logFromRequest($request, 'auth.login.success', [
            'description' => $user instanceof TeamMember
                ? 'Team login - successful'
                : 'Login - successful',
            'metadata' => [
                'guard' => $result->guard,
                'actor_name' => $actorName,
                'account_type' => $user instanceof TeamMember ? 'team' : 'owner',
            ],
        ]);

        if ($result->requiresTwoFactor) {
            return redirect()->route('two-factor.challenge');
        }

        return TeamPostLoginRedirect::intended();
    }

    public function destroy(): RedirectResponse
    {
        $guard = session(\App\Domains\Auth\Support\AuthSession::GUARD, 'web');
        $guard = is_string($guard) ? $guard : 'web';

        auth()->guard($guard)->logout();
        \App\Domains\Auth\Support\RememberTenantCookie::forget();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
