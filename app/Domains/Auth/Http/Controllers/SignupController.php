<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\SignupStepRequest;
use App\Domains\Auth\Services\SignupSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SignupController extends Controller
{
    /** @var array<int, array{key: string, route: string, next: string}> */
    private const STEPS = [
        1 => ['key' => 'meta_access', 'route' => 'signup.step-1', 'next' => 'signup.step-2'],
        2 => ['key' => 'has_website', 'route' => 'signup.step-2', 'next' => 'signup.step-3'],
        3 => ['key' => 'business_terms', 'route' => 'signup.step-3', 'next' => 'signup.step-4'],
        4 => ['key' => 'commerce_policy', 'route' => 'signup.step-4', 'next' => 'signup.step-5'],
        5 => ['key' => 'manager_verified', 'route' => 'signup.step-5', 'next' => 'signup.register'],
    ];

    public function showStep(int $step): View
    {
        abort_unless(isset(self::STEPS[$step]), 404);

        $view = "auth.signup.step-{$step}";
        $data = ['step' => $step];

        if ($step === 5) {
            $data['showModal'] = request('modal') === 'info';
        }

        return view($view, $data);
    }

    public function storeStep(SignupStepRequest $request, int $step, SignupSessionService $signupSession): RedirectResponse
    {
        abort_unless(isset(self::STEPS[$step]), 404);

        $config = self::STEPS[$step];
        $signupSession->merge([$config['key'] => $request->string('answer')->toString()]);

        if ($request->string('answer')->toString() !== 'yes') {
            return back()->withErrors([
                'answer' => 'You must confirm this requirement to continue with WapApp onboarding.',
            ]);
        }

        if ($step === 5) {
            return redirect()->route('signup.step-5', ['modal' => 'info']);
        }

        return redirect()->route($config['next']);
    }
}
