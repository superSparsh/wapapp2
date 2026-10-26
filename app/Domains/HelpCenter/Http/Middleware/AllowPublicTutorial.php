<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Http\Middleware;

use App\Domains\Auth\Http\Middleware\EnsureTwoFactorVerified;
use App\Domains\Integration\Http\Middleware\EnsureWabaBound;
use App\Domains\Integration\Http\Middleware\RestrictLineContextRoutes;
use App\Domains\Team\Http\Middleware\EnsureAccountOwner;
use App\Domains\Team\Http\Middleware\RedirectTeamMemberDashboard;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Symfony\Component\HttpFoundation\Response;

class AllowPublicTutorial
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExactPublicUrl($request)) {
            $request->attributes->set('tutorial_public_share', true);

            return $next($request);
        }

        return (new Pipeline(app()))
            ->send($request)
            ->through([
                Authenticate::class.':web,team',
                EnsureTwoFactorVerified::class,
                RedirectTeamMemberDashboard::class,
                EnsureWabaBound::class,
                RestrictLineContextRoutes::class,
                EnsureAccountOwner::class,
            ])
            ->then($next);
    }

    private function isExactPublicUrl(Request $request): bool
    {
        if (! $request->routeIs('tutorials.index')) {
            return false;
        }

        $publicId = (int) config('help-center.public_tutorial_video_id');

        if ($publicId < 1) {
            return false;
        }

        $query = $request->query();

        if (count($query) !== 1 || ! array_key_exists('video_id', $query)) {
            return false;
        }

        return (string) $query['video_id'] === (string) $publicId;
    }
}
