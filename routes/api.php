<?php

declare(strict_types=1);

use App\Domains\AiBot\Http\Controllers\AiInternalUsageController;
use App\Domains\Api\Http\Controllers\V1\CampaignApiController;
use App\Domains\Api\Http\Controllers\V1\ListApiController;
use App\Domains\Api\Http\Controllers\V1\SubscriberApiController;
use App\Domains\Api\Http\Middleware\AuthenticateApiToken;
use App\Domains\Drip\Http\Controllers\DripTriggerApiController;
use App\Domains\Forms\Http\Controllers\FormsOnboardingController;
use App\Domains\Notifications\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/drip/{campaign}/trigger', DripTriggerApiController::class)
    ->middleware(AuthenticateApiToken::class)
    ->name('api.drip.trigger');

Route::post('/ai/internal/log-usage', AiInternalUsageController::class)
    ->name('api.ai.internal.log-usage');

// Forms site (forms.tekprocloud.com) → create/reuse customer account (legacy /api/forms/onboarding).
Route::post('/forms/onboarding', [FormsOnboardingController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('api.forms.onboarding');

Route::prefix('v1')
    ->middleware(AuthenticateApiToken::class)
    ->group(function (): void {
        Route::get('/campaigns', [CampaignApiController::class, 'index']);
        Route::post('/campaigns', [CampaignApiController::class, 'store']);

        Route::get('/lists', [ListApiController::class, 'index']);

        Route::get('/subscribers', [SubscriberApiController::class, 'index']);
        Route::post('/subscribers', [SubscriberApiController::class, 'store']);

        // Mobile / API clients — FCM device token register & revoke (legacy parity).
        Route::post('/device-token', [DeviceTokenController::class, 'store']);
        Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);
        Route::post('/mobile/device-token', [DeviceTokenController::class, 'store']);
        Route::delete('/mobile/device-token', [DeviceTokenController::class, 'destroy']);
    });
