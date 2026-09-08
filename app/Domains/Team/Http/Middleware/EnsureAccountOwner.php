<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Middleware;

use App\Domains\Team\Support\TeamActor;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TeamActor::isOwner()) {
            throw new AuthorizationException('Only account owners can access this area.');
        }

        return $next($request);
    }
}
