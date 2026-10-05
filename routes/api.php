<?php

declare(strict_types=1);

use App\Domains\AiBot\Http\Controllers\AiInternalUsageController;
use App\Domains\Api\Http\Controllers\V1\BusinessApiController;
use App\Domains\Api\Http\Controllers\V1\CampaignApiController;
use App\Domains\Api\Http\Controllers\V1\DirectMessageApiController;
use App\Domains\Api\Http\Controllers\V1\ListApiController;
use App\Domains\Api\Http\Controllers\V1\LoginTokenApiController;
use App\Domains\Api\Http\Controllers\V1\StatisticsApiController;
use App\Domains\Api\Http\Controllers\V1\SubscriberApiController;
use App\Domains\Api\Http\Controllers\V1\SubscriptionApiController;
use App\Domains\Api\Http\Controllers\V1\TemplatePartnerApiController;
use App\Domains\Api\Http\Controllers\V1\VariableApiController;
use App\Domains\Api\Http\Middleware\AuthenticateApiToken;
use App\Domains\Drip\Http\Controllers\DripTriggerApiController;
use App\Domains\Forms\Http\Controllers\FormsOnboardingController;
use App\Domains\MobileApi\Http\Controllers\WalletController;
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
        // Auth
        Route::post('/login-token', [LoginTokenApiController::class, 'store']);

        // Direct message
        Route::post('/directmessage', [DirectMessageApiController::class, 'send']);
        Route::get('/getstatusofmessage', [DirectMessageApiController::class, 'status']);

        // Lists
        Route::get('/lists', [ListApiController::class, 'index']);
        Route::post('/lists', [ListApiController::class, 'store']);
        Route::get('/lists/{uid}', [ListApiController::class, 'show']);
        Route::delete('/lists/{uid}', [ListApiController::class, 'destroy']);

        // Subscribers
        Route::get('/subscribers', [SubscriberApiController::class, 'index']);
        Route::post('/subscribers', [SubscriberApiController::class, 'store']);
        Route::get('/subscribers/{uid}', [SubscriberApiController::class, 'show']);
        Route::patch('/subscribers/{uid}', [SubscriberApiController::class, 'update']);
        Route::delete('/subscribers/{uid}', [SubscriberApiController::class, 'destroy']);

        // Subscription
        Route::get('/subscription-details', [SubscriptionApiController::class, 'show']);

        // Variables
        Route::get('/variables', [VariableApiController::class, 'index']);
        Route::post('/variables', [VariableApiController::class, 'store']);

        // Templates
        Route::get('/templates', [TemplatePartnerApiController::class, 'index']);
        Route::get('/templates/{uid}', [TemplatePartnerApiController::class, 'show']);

        // Campaigns
        Route::get('/campaigns', [CampaignApiController::class, 'index']);
        Route::get('/campaigns/{uid}', [CampaignApiController::class, 'show']);
        Route::post('/campaigns', [CampaignApiController::class, 'store']);

        // Statistics
        Route::get('/business-conversations/statistics', [StatisticsApiController::class, 'businessConversations']);
        Route::get('/service-conversations/statistics', [StatisticsApiController::class, 'serviceConversations']);

        // Wallet (also available via JWT on mobile routes)
        Route::get('/wallet-transactions', [WalletController::class, 'transactions']);

        // Business
        Route::get('/business-details-and-phones', [BusinessApiController::class, 'detailsAndPhones']);

        // Partner API clients - FCM device token register & revoke.
        Route::post('/device-token', [DeviceTokenController::class, 'store']);
        Route::delete('/device-token', [DeviceTokenController::class, 'destroy']);
    });
