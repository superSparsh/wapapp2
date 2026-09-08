<?php

declare(strict_types=1);

use App\Domains\Api\Http\Controllers\V1\CampaignApiController;
use App\Domains\Api\Http\Controllers\V1\ListApiController;
use App\Domains\Api\Http\Controllers\V1\SubscriberApiController;
use App\Domains\Api\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(AuthenticateApiToken::class)
    ->group(function (): void {
        Route::get('/campaigns', [CampaignApiController::class, 'index']);
        Route::post('/campaigns', [CampaignApiController::class, 'store']);

        Route::get('/lists', [ListApiController::class, 'index']);

        Route::get('/subscribers', [SubscriberApiController::class, 'index']);
        Route::post('/subscribers', [SubscriberApiController::class, 'store']);
    });
