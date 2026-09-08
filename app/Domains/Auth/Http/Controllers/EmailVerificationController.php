<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\EmailVerificationRequest;
use App\Domains\Auth\Services\EmailVerificationService;
use App\Domains\Auth\Services\TenantResolver;
use App\Http\Controllers\Controller;
use App\Models\TenantUserAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function show(TenantResolver $tenantResolver): View|RedirectResponse
    {
        $email = session('pending_verification_email') ?? auth('web')->user()?->email;

        if ($email === null) {
            return redirect()->route('signup.step-1');
        }

        $access = TenantUserAccess::findActiveByEmail($email);

        if ($access !== null) {
            $tenantResolver->initializeForEmail($email);
        }

        $user = User::query()->where('email', $email)->first();

        return view('auth.signup.email', [
            'email' => $email,
            'otpSent' => request()->boolean('sent'),
            'verified' => $user?->hasVerifiedEmail() ?? false,
        ]);
    }

    public function resend(EmailVerificationService $verificationService, TenantResolver $tenantResolver): RedirectResponse
    {
        $email = session('pending_verification_email') ?? auth('web')->user()?->email;

        if ($email === null) {
            return redirect()->route('signup.step-1');
        }

        $tenantResolver->initializeForEmail($email);
        $user = User::query()->where('email', $email)->firstOrFail();
        $verificationService->sendActivationCode($user);

        return redirect()->route('signup.email', ['sent' => 1]);
    }

    public function verify(EmailVerificationRequest $request, EmailVerificationService $verificationService, TenantResolver $tenantResolver): RedirectResponse
    {
        $email = session('pending_verification_email') ?? auth('web')->user()?->email;

        if ($email === null) {
            return redirect()->route('signup.step-1');
        }

        $tenantResolver->initializeForEmail($email);
        $user = User::query()->where('email', $email)->firstOrFail();

        if (! $verificationService->verify($user, $request->string('code')->toString())) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        session()->forget('pending_verification_email');

        return redirect()->route('login')->with('status', 'Email verified successfully. You can now sign in.');
    }
}
