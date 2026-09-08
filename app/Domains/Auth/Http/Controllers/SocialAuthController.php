<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectGoogle(): RedirectResponse
    {
        return Socialite::driver('google')
            ->redirectUrl((string) config('services.google.redirect'))
            ->redirect();
    }

    public function callbackGoogle(): RedirectResponse
    {
        $user = Socialite::driver('google')
            ->redirectUrl((string) config('services.google.redirect'))
            ->user();

        return redirect()
            ->route('login')
            ->with('status', 'Google sign-in received for '.$user->getEmail().' (stub).');
    }

    public function redirectFacebook(): RedirectResponse
    {
        return Socialite::driver('facebook')
            ->redirectUrl((string) config('services.facebook.redirect'))
            ->redirect();
    }

    public function callbackFacebook(): RedirectResponse
    {
        $user = Socialite::driver('facebook')
            ->redirectUrl((string) config('services.facebook.redirect'))
            ->user();

        return redirect()
            ->route('login')
            ->with('status', 'Facebook sign-in received for '.$user->getEmail().' (stub).');
    }
}
