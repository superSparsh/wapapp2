<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Team\Services\TeamImpersonationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class TeamImpersonationController extends Controller
{
    public function stop(TeamImpersonationService $impersonationService): RedirectResponse
    {
        $impersonationService->stop();

        return redirect()
            ->route('manager.team.index')
            ->with('status', 'Returned to your manager account.');
    }
}
