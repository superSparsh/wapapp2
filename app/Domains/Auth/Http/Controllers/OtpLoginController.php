<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Exceptions\AccountInactiveException;
use App\Domains\Auth\Exceptions\InvalidOtpException;
use App\Domains\Auth\Exceptions\OtpDeliveryException;
use App\Domains\Auth\Exceptions\OtpExpiredException;
use App\Domains\Auth\Exceptions\PhoneNotRegisteredException;
use App\Domains\Auth\Http\Requests\SendLoginOtpRequest;
use App\Domains\Auth\Http\Requests\VerifyLoginOtpRequest;
use App\Domains\Auth\Services\LoginOtpService;
use App\Domains\Team\Support\TeamPostLoginRedirect;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class OtpLoginController extends Controller
{
    public function send(SendLoginOtpRequest $request, LoginOtpService $loginOtpService): JsonResponse
    {
        try {
            $loginOtpService->send($request->string('phone')->toString());
        } catch (PhoneNotRegisteredException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 404);
        } catch (OtpDeliveryException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function verify(VerifyLoginOtpRequest $request, LoginOtpService $loginOtpService): RedirectResponse
    {
        try {
            $result = $loginOtpService->verify(
                phone: $request->string('phone')->toString(),
                otp: $request->string('otp')->toString(),
                remember: $request->boolean('remember'),
            );
        } catch (PhoneNotRegisteredException|InvalidOtpException|OtpExpiredException|AccountInactiveException $exception) {
            return redirect()
                ->route('login', ['tab' => 'mobile'])
                ->withInput($request->only('phone', 'remember'))
                ->withErrors(['otp' => $exception->getMessage()]);
        }

        if ($result->requiresTwoFactor) {
            return redirect()->route('two-factor.challenge');
        }

        return TeamPostLoginRedirect::intended();
    }
}
