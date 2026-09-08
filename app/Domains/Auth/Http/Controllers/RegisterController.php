<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\RegisterProfileRequest;
use App\Domains\Auth\Http\Requests\RegisterRequest;
use App\Domains\Auth\Services\RegisterService;
use App\Domains\Auth\Services\SignupSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(SignupSessionService $signupSession): View|RedirectResponse
    {
        if (! $signupSession->hasCompletedPreRegistration()) {
            return redirect()->route('signup.step-1')
                ->withErrors(['signup' => 'Please complete all onboarding questions first.']);
        }

        $variant = request('variant', 'default');
        $allowed = ['default', 'business-details', 'whatsapp', 'success'];

        if (! in_array($variant, $allowed, true)) {
            abort(404);
        }

        return view('auth.signup.register', ['variant' => $variant]);
    }

    public function storeProfile(RegisterProfileRequest $request, SignupSessionService $signupSession): RedirectResponse
    {
        $signupSession->merge($request->validated());

        return redirect()->route('signup.register', ['variant' => 'business-details']);
    }

    public function store(RegisterRequest $request, RegisterService $registerService, SignupSessionService $signupSession): RedirectResponse
    {
        $payload = array_merge($signupSession->all(), $request->validated());

        try {
            $user = $registerService->register($payload);
        } catch (\InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['work_email' => $exception->getMessage()]);
        } catch (\RuntimeException $exception) {
            return redirect()->route('signup.step-1')->withErrors(['signup' => $exception->getMessage()]);
        }

        session(['pending_verification_email' => $user->email]);

        return redirect()->route('signup.email', ['sent' => 1]);
    }
}
