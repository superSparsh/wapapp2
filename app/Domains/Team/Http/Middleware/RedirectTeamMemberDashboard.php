<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Middleware;

use App\Domains\Team\Services\TeamRedirectService;
use App\Domains\Team\Support\TeamActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectTeamMemberDashboard
{
    public function __construct(
        private readonly TeamRedirectService $redirectService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $member = TeamActor::teamMember();

        if ($member !== null && $request->routeIs('dashboard*')) {
            return redirect($this->redirectService->landingUrl($member));
        }

        return $next($request);
    }
}
