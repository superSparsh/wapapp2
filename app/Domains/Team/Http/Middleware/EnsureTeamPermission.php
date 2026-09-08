<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Middleware;

use App\Domains\Team\Support\TeamActor;
use App\Domains\Team\Support\TeamPermissions;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (TeamActor::isOwner()) {
            return $next($request);
        }

        $member = TeamActor::teamMember();

        if ($member === null || ! TeamPermissions::isEnabled($member->permissions, $permission)) {
            throw new AuthorizationException('You do not have access to this module.');
        }

        return $next($request);
    }
}
