<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Controllers;

use App\Domains\Integration\Services\OnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    public function show(): RedirectResponse
    {
        if ($this->onboardingService->isComplete()) {
            return redirect()->route('dashboard');
        }

        if (session('onboarding.fb_app_id') || $this->onboardingService->existingIsvTerms()) {
            return redirect()->route('onboarding.connect');
        }

        return redirect()->route('onboarding.business');
    }

    public function business(): View|RedirectResponse
    {
        if ($this->onboardingService->isComplete()) {
            return redirect()->route('dashboard');
        }

        return view('onboarding.business', [
            'terms' => $this->onboardingService->existingIsvTerms() ?? [],
        ]);
    }

    public function connect(): View|RedirectResponse
    {
        if ($this->onboardingService->isComplete()) {
            return redirect()->route('dashboard');
        }

        if (! $this->onboardingService->existingIsvTerms()) {
            return redirect()
                ->route('onboarding.business')
                ->with('error', 'Please save your business details first.');
        }

        $appId = session('onboarding.fb_app_id');
        if (! filled($appId)) {
            $appId = app(\App\Domains\WhatsApp\Services\AlibabaCamsClient::class)->isvGetAppId();
            if (filled($appId)) {
                session(['onboarding.fb_app_id' => $appId]);
            }
        }

        return view('onboarding.connect', [
            'appId' => $appId,
            'embedUrl' => route('onboarding.embed-data'),
            'finishUrl' => route('onboarding.finish'),
        ]);
    }

    public function finish(): View|RedirectResponse
    {
        if (! $this->onboardingService->isComplete()) {
            return redirect()->route('onboarding.start');
        }

        return view('onboarding.finish');
    }

    public function isvTerms(): JsonResponse
    {
        $data = $this->onboardingService->existingIsvTerms();

        if ($data === null) {
            return response()->json([
                'status' => 'not_found',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function storeIsvTerms(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $validated = $request->validate([
                'business_name' => ['required', 'string', 'max:255'],
                'bm_id' => ['required', 'string', 'max:255'],
                'use_case' => ['required', 'string', 'max:255'],
                'business_address' => ['required', 'string', 'max:1000'],
                'website_email' => ['required', 'email', 'max:255'],
            ]);

            $result = $this->onboardingService->saveIsvTerms($validated);

            session([
                'onboarding.fb_app_id' => $result['app_id'],
            ]);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Business details saved successfully.',
                    'data' => $result['term'],
                    'app_id' => $result['app_id'],
                ]);
            }

            return redirect()
                ->route('onboarding.connect')
                ->with('status', 'Business details saved successfully.');
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'validation_error',
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        } catch (Throwable $e) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Something went wrong.',
                    'debug' => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Something went wrong while saving business details.');
        }
    }

    public function embedData(Request $request): JsonResponse
    {
        try {
            $wabaId = trim((string) $request->input('waba_id', ''));
            $phoneNumberId = $request->input('phone_number_id');
            $phoneNumberId = is_scalar($phoneNumberId) ? (string) $phoneNumberId : null;

            $user = $request->user('web') ?? $request->user();

            $this->onboardingService->completeEmbeddedSignup(
                wabaId: $wabaId,
                phoneNumberId: $phoneNumberId !== '' ? $phoneNumberId : null,
                user: $user instanceof \App\Models\User ? $user : null,
            );

            session()->forget('onboarding.fb_app_id');

            return response()->json([
                'status' => '200',
                'msg' => 'success',
                'redirect' => route('onboarding.finish'),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => '404',
                'msg' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'It seems that something went wrong. Please try again later.',
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
