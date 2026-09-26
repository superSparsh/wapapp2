<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Http\Requests\UpdateProfileRequest;
use App\Domains\Account\Services\ProfileService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'avatarMaxMb' => round(((int) config('account.avatar.max_kb', 2048)) / 1024, 1),
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

    public function showAvatar(string $path, ProfileService $profileService): StreamedResponse
    {
        $this->ownerUser();

        return $profileService->streamAvatar($path);
    }

    private function ownerUser(): User
    {
        $user = auth('web')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
