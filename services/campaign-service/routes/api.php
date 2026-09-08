<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CampaignActionController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\CampaignStatsController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Middleware\AuthenticateServiceRequest;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', HealthController::class);

Route::prefix('v1')
    ->middleware([
        CorrelationIdMiddleware::class,
        AuthenticateServiceRequest::class,
        SetTenantContext::class,
    ])
    ->group(function (): void {
        // Campaigns CRUD
        Route::get('/campaigns', [CampaignController::class, 'index']);
        Route::post('/campaigns', [CampaignController::class, 'store']);
        Route::get('/campaigns/{uuid}', [CampaignController::class, 'show']);
        Route::put('/campaigns/{uuid}', [CampaignController::class, 'update']);
        Route::delete('/campaigns/{uuid}', [CampaignController::class, 'destroy']);

        // Lifecycle & State Actions
        Route::post('/campaigns/{uuid}/launch', [CampaignController::class, 'launch']);
        Route::post('/campaigns/{uuid}/toggle', [CampaignController::class, 'toggle']);
        Route::post('/campaigns/{uuid}/duplicate', [CampaignController::class, 'duplicate']);

        // Extended Campaign Actions
        Route::post('/campaigns/{uuid}/test-message', [CampaignActionController::class, 'testMessage']);
        Route::post('/campaigns/{uuid}/resend-failed', [CampaignActionController::class, 'resendFailed']);
        Route::get('/campaigns/{uuid}/cost', [CampaignActionController::class, 'calculateCost']);
        Route::post('/campaigns/{uuid}/import-recipients', [CampaignActionController::class, 'importRecipients']);
        Route::post('/campaigns/{uuid}/recipients', [CampaignActionController::class, 'populateRecipients']);
        Route::post('/campaigns/{uuid}/webhooks', [CampaignActionController::class, 'storeWebhook']);

        // Statistics & Export
        Route::get('/campaigns/{uuid}/statistics', [CampaignStatsController::class, 'overview']);
        Route::get('/campaigns/{uuid}/recipients', [CampaignStatsController::class, 'recipientLog']);
        Route::get('/campaigns/{uuid}/export', [CampaignStatsController::class, 'export']);

        // Scheduled batch execution trigger
        Route::post('/campaigns/process-due', [CampaignActionController::class, 'processDue']);
    });
