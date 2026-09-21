<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Team\Services\TeamAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerTeamSettingsController extends Controller
{
    public function edit(TeamAccessService $accessService): View
    {
        $owner = $accessService->owner();

        return view('my-team.settings', [
            'autoAssignChats' => (bool) $owner->auto_assign_chats,
        ]);
    }

    public function update(Request $request, TeamAccessService $accessService): RedirectResponse
    {
        $owner = $accessService->owner();
        $owner->auto_assign_chats = $request->boolean('auto_assign_chats');
        $owner->save();

        return redirect()
            ->route('my-team.settings')
            ->with('status', 'Team settings saved.');
    }
}
