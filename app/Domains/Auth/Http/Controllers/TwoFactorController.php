<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Contracts\TwoFactorAuthenticatable;
use App\Domains\Account\Services\ActivityLogService;
use App\Domains\Auth\Http\Requests\TwoFactorChallengeRequest;
use App\Domains\Auth\Support\AuthSession;
use App\Domains\Team\Support\TeamPostLoginRedirect;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function challenge(): View|RedirectResponse
    {
        $user = $this->authenticatedUser();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->hasTwoFactorEnabled()) {
            session([AuthSession::TWO_FACTOR_VERIFIED => true]);

            return TeamPostLoginRedirect::intended();
        }

        if (session(AuthSession::TWO_FACTOR_VERIFIED)) {
            return TeamPostLoginRedirect::intended();
        }

        return view('auth.two-factor-challenge');
    }

    public function verify(
        TwoFactorChallengeRequest $request,
        ActivityLogService $activityLogService,
    ): RedirectResponse {
        $user = $this->authenticatedUser();

        if ($user === null) {
            return redirect()->route('login');
        }

        $code = $request->string('code')->toString();

        if ($user->verifyTwoFactorCode($code)) {
            $usedRecovery = false;
            $verified = true;
        } elseif ($user->consumeRecoveryCode($code)) {
            $usedRecovery = true;
            $verified = true;
        } else {
            $verified = false;
            $usedRecovery = false;
        }

        if (! $verified) {
            $activityLogService->logFromRequest($request, 'auth.2fa.challenge_failed', [
                'scope' => 'security',
            ]);

            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        session([AuthSession::TWO_FACTOR_VERIFIED => true]);

        $activityLogService->logFromRequest(
            $request,
            $usedRecovery ? 'auth.2fa.recovery_used' : 'auth.2fa.challenge_success',
            ['scope' => 'security'],
        );

        return TeamPostLoginRedirect::intended();
    }

    private function authenticatedUser(): ?TwoFactorAuthenticatable
    {
        $user = auth('web')->user() ?? auth('team')->user();

        return $user instanceof TwoFactorAuthenticatable ? $user : null;
    }
}
