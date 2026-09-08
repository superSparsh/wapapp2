<?php

declare(strict_types=1);

namespace App\Domains\Team\Http\Controllers;

use App\Domains\Team\Services\TeamAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerSettingsController extends Controller
{
    public function edit(TeamAccessService $accessService): View
    {
        $manager = $accessService->manager();

        return view('manager.settings', [
            'manager' => $manager,
            'autoAssignChats' => (bool) $manager->auto_assign_chats,
        ]);
    }

    public function update(Request $request, TeamAccessService $accessService): RedirectResponse
    {
        $manager = $accessService->manager();
        $manager->auto_assign_chats = $request->boolean('auto_assign_chats');
        $manager->save();

        return redirect()
            ->route('manager.settings')
            ->with('status', 'Manager settings saved.');
    }
}
