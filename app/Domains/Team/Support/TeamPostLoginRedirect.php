<?php

declare(strict_types=1);

namespace App\Domains\Team\Support;

use App\Domains\Team\Services\TeamRedirectService;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;

final class TeamPostLoginRedirect
{
    public static function intended(): RedirectResponse
    {
        $member = TeamActor::teamMember();

        if ($member instanceof TeamMember) {
            return redirect()->intended(app(TeamRedirectService::class)->landingUrl($member));
        }

        return redirect()->intended(route('dashboard'));
    }
}
