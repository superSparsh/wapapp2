<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Middleware;

use App\Domains\Team\Support\TeamActor;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $manager = TeamActor::teamMember();

        if ($manager === null || ! $manager->isManager()) {
            throw new AuthorizationException('Manager access required.');
        }

        return $next($request);
    }
}
