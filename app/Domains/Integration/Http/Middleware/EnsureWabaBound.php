<?php

declare(strict_types=1);

namespace App\Domains\Integration\Http\Middleware;

use App\Domains\Integration\Services\OnboardingService;
use App\Domains\Integration\Services\PhoneLineService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWabaBound
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Team members (and line-locked sessions) skip the owner onboarding gate.
        if ($request->user('team') && ! $request->user('web')) {
            return $next($request);
        }

        if (! $request->user('web') && ! $request->user()) {
            return $next($request);
        }

        if ($request->is('logout') || $request->routeIs('logout')) {
            return $next($request);
        }

        if ($request->is('login') || $request->routeIs('login')) {
            return redirect()->route('dashboard');
        }

        if ($this->onboardingService->isComplete()) {
            return $next($request);
        }

        if (PhoneLineService::isLocked()) {
            return $next($request);
        }

        $isOnboardingRoute = $request->is('onboarding')
            || $request->is('onboarding/*')
            || $request->routeIs('onboarding.*');

        $isIntegrationsApi = $request->is('integrations')
            || $request->is('integrations/*');

        $isAccountRoute = $request->is('profile')
            || $request->is('profile/*')
            || $request->routeIs('profile.*')
            || $request->routeIs('subscription.*');

        $isJsonRequest = $request->expectsJson() || $request->wantsJson();

        if ($isOnboardingRoute || $isIntegrationsApi || $isAccountRoute || $isJsonRequest) {
            return $next($request);
        }

        return redirect()->route('onboarding.start');
    }
}
