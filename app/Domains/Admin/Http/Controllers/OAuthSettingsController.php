<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OAuthSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.oauth.edit', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'oauth_google_enabled' => ['nullable', 'in:0,1'],
            'oauth_google_client_id' => ['nullable', 'string', 'max:191'],
            'oauth_google_client_secret' => ['nullable', 'string', 'max:191'],
            'oauth_facebook_enabled' => ['nullable', 'in:0,1'],
            'oauth_facebook_client_id' => ['nullable', 'string', 'max:191'],
            'oauth_facebook_client_secret' => ['nullable', 'string', 'max:191'],
        ]);

        $this->settings->save([
            'oauth.google_enabled' => $validated['oauth_google_enabled'] ?? '0',
            'oauth.google_client_id' => $validated['oauth_google_client_id'] ?? '',
            'oauth.google_client_secret' => $validated['oauth_google_client_secret'] ?? '',
            'oauth.facebook_enabled' => $validated['oauth_facebook_enabled'] ?? '0',
            'oauth.facebook_client_id' => $validated['oauth_facebook_client_id'] ?? '',
            'oauth.facebook_client_secret' => $validated['oauth_facebook_client_secret'] ?? '',
        ]);

        return back()->with('status', 'OAuth settings saved.');
    }
}
