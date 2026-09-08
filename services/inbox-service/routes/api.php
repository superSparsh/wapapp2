<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ActionController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\ThreadController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Middleware\AuthenticateServiceRequest;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

Route::prefix('v1')
    ->middleware([
        CorrelationIdMiddleware::class,
        AuthenticateServiceRequest::class,
        SetTenantContext::class,
    ])
    ->group(function (): void {
        // Health check
        Route::get('/health', [HealthController::class, 'check']);

        // Threads & Conversations
        Route::get('/threads', [ThreadController::class, 'index']);
        Route::get('/unread-count', [ThreadController::class, 'unreadCount']);
        Route::post('/contacts', [ContactController::class, 'store']);

        // Mass actions
        Route::post('/mark-all-read', [ActionController::class, 'markAllRead']);
        Route::post('/response-type/all', [ActionController::class, 'toggleAllResponseType']);
        Route::get('/export', [ExportController::class, 'exportAll']);

        // Webhook ingestion
        Route::post('/inbound', [WebhookController::class, 'recordInbound']);
        Route::post('/delivery-status', [WebhookController::class, 'updateDeliveryStatus']);

        // Conversation scoped actions
        Route::prefix('conversations/{conversation}')->group(function (): void {
            Route::get('/messages', [MessageController::class, 'index']);
            Route::post('/messages', [MessageController::class, 'sendMessage']);
            Route::post('/media', [MessageController::class, 'sendMedia']);
            Route::post('/templates', [MessageController::class, 'sendTemplate']);
            Route::post('/location', [MessageController::class, 'sendLocation']);
            Route::post('/sticker', [MessageController::class, 'sendSticker']);
            Route::post('/read', [ActionController::class, 'markRead']);
            Route::post('/assign', [ActionController::class, 'assign']);
            Route::post('/response-type', [ActionController::class, 'toggleResponseType']);
            Route::get('/window', [ActionController::class, 'windowStatus']);
            Route::get('/export', [ExportController::class, 'exportConversation']);
        });
    });
