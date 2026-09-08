<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Http\Requests\SyncNotificationContactsRequest;
use App\Domains\Account\Services\NotificationContactService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationContactController extends Controller
{
    public function show(NotificationContactService $notificationContactService): View
    {
        $user = $this->ownerUser();

        return view('profile.alerts', [
            'preferences' => $notificationContactService->preferences(),
            'contacts' => $notificationContactService->listForUser($user),
            'notificationTypes' => config('account.notification_types', []),
        ]);
    }

    public function sync(SyncNotificationContactsRequest $request, NotificationContactService $notificationContactService): RedirectResponse
    {
        $user = $this->ownerUser();

        $notificationContactService->syncForUser(
            user: $user,
            rows: $request->validated('contacts', []),
            alertsEnabled: $request->boolean('alerts_enabled'),
        );

        return redirect()
            ->route('profile.alerts')
            ->with('status', 'Notification contacts saved successfully.');
    }

    private function ownerUser(): User
    {
        $user = auth('web')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
