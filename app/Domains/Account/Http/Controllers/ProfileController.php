<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Http\Requests\UpdateProfileRequest;
use App\Domains\Account\Services\ProfileService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(ProfileService $profileService): View
    {
        $user = $this->ownerUser();

        return view('profile.index', [
            'profile' => $profileService->forUser($user),
            'timezones' => config('account.timezones', []),
            'countries' => config('account.countries', []),
            'locales' => config('account.locales', []),
        ]);
    }

    public function update(UpdateProfileRequest $request, ProfileService $profileService): RedirectResponse
    {
        $user = $this->ownerUser();

        $profileService->update(
            user: $user,
            data: $request->validated(),
            avatar: $request->file('avatar'),
            removeAvatar: $request->boolean('remove_avatar'),
        );

        return redirect()
            ->route('profile.index')
            ->with('status', 'Profile updated successfully.');
    }

    private function ownerUser(): User
    {
        $user = auth('web')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
