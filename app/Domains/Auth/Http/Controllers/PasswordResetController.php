<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Http\Requests\ResetPasswordRequest;
use App\Domains\Auth\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(ForgotPasswordRequest $request, PasswordResetService $passwordResetService): RedirectResponse
    {
        $status = $passwordResetService->sendResetLink($request->string('email')->toString());

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput()->withErrors(['email' => __($status)]);
    }

    public function showReset(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function reset(ResetPasswordRequest $request, PasswordResetService $passwordResetService): RedirectResponse
    {
        $status = $passwordResetService->reset(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            token: $request->string('token')->toString(),
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withInput()->withErrors(['email' => __($status)]);
    }
}
