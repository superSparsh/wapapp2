<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Services\ApiTokenService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    public function show(ApiTokenService $apiTokenService): View
    {
        $user = $this->ownerUser();
        $token = $apiTokenService->ensure($user);

        return view('profile.api', [
            'token' => $token,
            'plainToken' => session('plain_api_token'),
            'docsUrl' => route('profile.api.docs'),
            'baseUrl' => rtrim((string) config('account.api.base_url'), '/'),
            'exampleUrl' => rtrim((string) config('account.api.base_url'), '/').'/lists?api_token='.$token,
        ]);
    }

    public function renew(ApiTokenService $apiTokenService): RedirectResponse
    {
        $user = $this->ownerUser();
        $plainToken = $apiTokenService->renew($user);

        return redirect()
            ->route('profile.api')
            ->with('status', 'API token renewed successfully.')
            ->with('plain_api_token', $plainToken);
    }

    private function ownerUser(): User
    {
        $user = auth('web')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
