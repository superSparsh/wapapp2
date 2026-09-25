<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Middleware;

use App\Domains\Account\Services\ActivityLogService;
use App\Domains\Team\Support\TeamActor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write team-member mutating actions into the tenant activity log
 * so the account owner can see them under Profile → Activity Logs.
 */
class LogTeamMemberMutations
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! TeamActor::isTeamMember()) {
            return $response;
        }

        if ($request->isMethodSafe() || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        if (! is_string($routeName) || $routeName === '') {
            return $response;
        }

        // Skip auth/impersonation noise and team-management routes (manager portal).
        if (str_starts_with($routeName, 'team.impersonation.')
            || str_starts_with($routeName, 'manager.')
            || str_starts_with($routeName, 'login')
            || str_starts_with($routeName, 'logout')
        ) {
            return $response;
        }

        $member = TeamActor::teamMember();
        $verb = strtoupper($request->method());

        $this->activityLogService->logFromRequest($request, 'team.module.'.$verb.'.'.$routeName, [
            'scope' => 'account',
            'description' => $this->description($verb, $routeName),
            'metadata' => [
                'route' => $routeName,
                'method' => $verb,
                'actor_role' => $member?->role?->value,
            ],
        ]);

        return $response;
    }

    private function description(string $verb, string $routeName): string
    {
        $action = match ($verb) {
            'POST' => 'Created / submitted',
            'PUT', 'PATCH' => 'Updated',
            'DELETE' => 'Deleted',
            default => $verb,
        };

        return $action.' via '.$routeName;
    }
}
