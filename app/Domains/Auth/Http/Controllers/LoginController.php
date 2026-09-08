<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Auth\Exceptions\InvalidCredentialsException;
use App\Domains\Auth\Http\Requests\LoginRequest;
use App\Domains\Auth\Services\LoginService;
use App\Domains\Team\Support\TeamPostLoginRedirect;
use App\Http\Controllers\Controller;
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

    public function store(LoginRequest $request, LoginService $loginService): RedirectResponse
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

        if ($result->requiresTwoFactor) {
            return redirect()->route('two-factor.challenge');
        }

        return TeamPostLoginRedirect::intended();
    }

    public function destroy(): RedirectResponse
    {
        $guard = session(\App\Domains\Auth\Support\AuthSession::GUARD, 'web');
        auth()->guard(is_string($guard) ? $guard : 'web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
