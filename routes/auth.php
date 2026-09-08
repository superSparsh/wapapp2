<?php

declare(strict_types=1);

use App\Domains\Auth\Http\Controllers\EmailVerificationController;
use App\Domains\Auth\Http\Controllers\LoginController;
use App\Domains\Auth\Http\Controllers\OtpLoginController;
use App\Domains\Auth\Http\Controllers\PasswordResetController;
use App\Domains\Auth\Http\Controllers\RegisterController;
use App\Domains\Auth\Http\Controllers\SignupController;
use App\Domains\Auth\Http\Controllers\TwoFactorController;
use App\Domains\Auth\Http\Middleware\RedirectIfAuthenticated;
use App\Domains\Auth\Http\Requests\SignupStepRequest;
use App\Domains\Auth\Services\SignupSessionService;
use Illuminate\Support\Facades\Route;

Route::middleware(RedirectIfAuthenticated::class)->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::post('/login/otp/send', [OtpLoginController::class, 'send'])->name('login.otp.send');
    Route::post('/login/otp/verify', [OtpLoginController::class, 'verify'])->name('login.otp.verify');

    Route::get('/auth/google', [\App\Domains\Auth\Http\Controllers\SocialAuthController::class, 'redirectGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [\App\Domains\Auth\Http\Controllers\SocialAuthController::class, 'callbackGoogle'])->name('auth.google.callback');
    Route::get('/auth/facebook', [\App\Domains\Auth\Http\Controllers\SocialAuthController::class, 'redirectFacebook'])->name('auth.facebook');
    Route::get('/auth/facebook/callback', [\App\Domains\Auth\Http\Controllers\SocialAuthController::class, 'callbackFacebook'])->name('auth.facebook.callback');

    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

    Route::prefix('signup')->name('signup.')->group(function () {
        Route::redirect('/', '/signup/step-1');

        foreach ([1, 2, 3, 4, 5] as $step) {
            Route::get("/step-{$step}", fn () => app(SignupController::class)->showStep($step))
                ->name("step-{$step}");

            Route::post("/step-{$step}", function (SignupStepRequest $request) use ($step) {
                return app(SignupController::class)->storeStep(
                    $request,
                    $step,
                    app(SignupSessionService::class),
                );
            })->name("step-{$step}.store");
        }

        Route::get('/register', [RegisterController::class, 'show'])->name('register');
        Route::post('/register/profile', [RegisterController::class, 'storeProfile'])->name('register.profile');
        Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

        Route::get('/email', [EmailVerificationController::class, 'show'])->name('email');
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->name('email.resend');
        Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->name('email.verify');
    });
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware(['tenancy.session', 'auth:web,team'])
    ->name('logout');

Route::middleware(['tenancy.session', 'auth:web,team'])->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
});
