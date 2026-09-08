<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Contracts\TwoFactorAuthenticatable;
use App\Domains\Account\Http\Requests\DisableTwoFactorRequest;
use App\Domains\Account\Http\Requests\RegenerateRecoveryCodesRequest;
use App\Domains\Account\Services\ActivityLogService;
use App\Domains\Auth\Http\Requests\TwoFactorSetupRequest;
use App\Domains\Auth\Services\TwoFactorService;
use App\Domains\Auth\Support\AuthSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function show(TwoFactorService $twoFactorService): View
    {
        $user = $this->authenticatedUser();

        $secret = session('two_factor_setup_secret');
        $pendingRecoveryCodes = session('two_factor_setup_recovery_codes', []);

        if ((! is_string($secret) || $secret === '') && ! $user->hasTwoFactorEnabled()) {
            $secret = $twoFactorService->generateSecret();
            $pendingRecoveryCodes = $twoFactorService->generateRecoveryCodes();

            session([
                'two_factor_setup_secret' => $secret,
                'two_factor_setup_recovery_codes' => $pendingRecoveryCodes,
            ]);
        }

        return view('profile.security', [
            'secret' => is_string($secret) ? $secret : null,
            'qrCode' => is_string($secret) && $secret !== ''
                ? $twoFactorService->qrCodeSvg($user, $secret)
                : null,
            'enabled' => $user->hasTwoFactorEnabled(),
            'recoveryCodes' => session('two_factor_recovery_codes', []),
            'pendingRecoveryCodes' => $pendingRecoveryCodes,
        ]);
    }

    public function enable(
        TwoFactorSetupRequest $request,
        TwoFactorService $twoFactorService,
        ActivityLogService $activityLogService,
    ): RedirectResponse {
        $user = $this->authenticatedUser();

        $secret = session('two_factor_setup_secret');
        $recoveryCodes = session('two_factor_setup_recovery_codes', []);

        if (! is_string($secret) || $secret === '') {
            return redirect()
                ->route('profile.security')
                ->withErrors(['code' => 'Your setup session expired. Please scan the QR code again.']);
        }

        try {
            $recoveryCodes = $twoFactorService->enable(
                user: $user,
                secret: $secret,
                code: $request->string('code')->toString(),
                recoveryCodes: is_array($recoveryCodes) && $recoveryCodes !== [] ? $recoveryCodes : null,
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()]);
        }

        session()->forget(['two_factor_setup_secret', 'two_factor_setup_recovery_codes']);
        session([
            AuthSession::TWO_FACTOR_VERIFIED => true,
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        $activityLogService->logFromRequest($request, 'security.2fa.enabled');

        return redirect()
            ->route('profile.security')
            ->with('status', 'Two-factor authentication enabled.');
    }

    public function disable(
        DisableTwoFactorRequest $request,
        TwoFactorService $twoFactorService,
        ActivityLogService $activityLogService,
    ): RedirectResponse {
        $user = $this->authenticatedUser();

        try {
            $twoFactorService->disableWithVerification(
                user: $user,
                password: $request->string('password')->toString(),
                code: $request->string('code')->toString(),
            );
        } catch (\InvalidArgumentException $exception) {
            $field = str_contains(strtolower($exception->getMessage()), 'password') ? 'password' : 'code';

            return back()->withErrors([$field => $exception->getMessage()]);
        }

        session()->forget([
            AuthSession::TWO_FACTOR_VERIFIED,
            'two_factor_setup_secret',
            'two_factor_setup_recovery_codes',
            'two_factor_recovery_codes',
        ]);

        $activityLogService->logFromRequest($request, 'security.2fa.disabled');

        return redirect()
            ->route('profile.security')
            ->with('status', 'Two-factor authentication disabled.');
    }

    public function regenerateRecoveryCodes(
        RegenerateRecoveryCodesRequest $request,
        TwoFactorService $twoFactorService,
        ActivityLogService $activityLogService,
    ): RedirectResponse {
        $user = $this->authenticatedUser();

        try {
            $recoveryCodes = $twoFactorService->regenerateRecoveryCodes(
                $user,
                $request->string('code')->toString(),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['recovery_code' => $exception->getMessage()]);
        }

        session(['two_factor_recovery_codes' => $recoveryCodes]);

        $activityLogService->logFromRequest($request, 'security.2fa.recovery_regenerated');

        return redirect()
            ->route('profile.security')
            ->with('status', 'Recovery codes regenerated.');
    }

    private function authenticatedUser(): TwoFactorAuthenticatable
    {
        $user = auth('web')->user() ?? auth('team')->user();

        abort_unless($user instanceof TwoFactorAuthenticatable, 403);

        return $user;
    }
}
