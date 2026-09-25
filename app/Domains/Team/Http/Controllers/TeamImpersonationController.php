<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Team\Services\TeamImpersonationService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;

class TeamImpersonationController extends Controller
{
    public function stop(TeamImpersonationService $impersonationService): RedirectResponse
    {
        if (! $impersonationService->isImpersonating()) {
            throw new AuthorizationException('No active impersonation session.');
        }

        $route = $impersonationService->stopRedirectRoute();
        $wasOwner = $impersonationService->isOwnerImpersonating();

        $impersonationService->stop();

        return redirect()
            ->route($route)
            ->with(
                'status',
                $wasOwner
                    ? 'Returned to your account.'
                    : 'Returned to your manager account.',
            );
    }
}
