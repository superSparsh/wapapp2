<?php

declare(strict_types=1);

use App\Domains\MobileApi\Http\Controllers\AuthController;
use App\Domains\MobileApi\Http\Controllers\DashboardController;
use App\Domains\MobileApi\Http\Controllers\MobileInboxController;
use App\Domains\MobileApi\Http\Controllers\ProviderKeysController;
use App\Domains\MobileApi\Http\Controllers\WalletController;
use App\Domains\MobileApi\Http\Middleware\AuthenticateJwtOrApiToken;
use App\Domains\Notifications\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile JWT API (legacy parity)
|--------------------------------------------------------------------------
| Paths match legacy /api/v1/auth and /api/v1/mobile/* for the existing app.
*/

Route::prefix('v1/auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('otp-login', [AuthController::class, 'otpLogin']);
    Route::post('send-otp', [AuthController::class, 'sendOtp']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::middleware('jwt.auth')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Legacy wallet screen used by the mobile app (JWT or partner api_token).
Route::prefix('v1')->middleware(AuthenticateJwtOrApiToken::class)->group(function (): void {
    Route::get('wallet-transactions', [WalletController::class, 'transactions']);
});

// Device tokens: JWT (mobile app) or partner api_token.
Route::prefix('v1/mobile')->middleware(AuthenticateJwtOrApiToken::class)->group(function (): void {
    Route::post('device-token', [DeviceTokenController::class, 'store']);
    Route::delete('device-token', [DeviceTokenController::class, 'destroy']);
});

Route::prefix('v1/mobile')->middleware('jwt.auth')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'dashboard']);

    Route::prefix('inbox')->group(function (): void {
        Route::get('assigned-numbers', [MobileInboxController::class, 'getAssignedNumbers']);
        Route::post('assign-number', [MobileInboxController::class, 'assignNumber']);

        Route::get('conversations', [MobileInboxController::class, 'getConversations']);
        Route::get('conversations/paginated', [MobileInboxController::class, 'getConversationsPaginated']);
        Route::get('conversations/unread', [MobileInboxController::class, 'getUnreadConversations']);
        Route::get('conversations/search', [MobileInboxController::class, 'searchConversations']);
        Route::get('conversations/sub-reply/{sub_reply_id}', [MobileInboxController::class, 'getSubReplyConversations']);
        Route::post('conversations/multiple', [MobileInboxController::class, 'getMultipleConversations']);
        Route::post('conversations/export', [MobileInboxController::class, 'exportConversation']);
        Route::get('conversation', [MobileInboxController::class, 'getConversation']);
        Route::post('conversation/status', [MobileInboxController::class, 'updateConversationStatus']);

        Route::post('send-message', [MobileInboxController::class, 'sendMessage']);
        Route::post('send-media', [MobileInboxController::class, 'sendMedia']);
        Route::post('send-location', [MobileInboxController::class, 'sendLocation']);
        Route::post('send-contact', [MobileInboxController::class, 'sendContact']);
        Route::post('send-sticker', [MobileInboxController::class, 'sendSticker']);
        Route::post('send-interactive', [MobileInboxController::class, 'sendInteractive']);
        Route::post('send-flow', [MobileInboxController::class, 'sendFlow']);
        Route::post('send-address', [MobileInboxController::class, 'sendAddress']);
        Route::post('send-location-request', [MobileInboxController::class, 'sendLocationRequest']);
        Route::post('send-catalog', [MobileInboxController::class, 'sendCatalog']);

        Route::get('templates', [MobileInboxController::class, 'getTemplates']);
        Route::post('send-template', [MobileInboxController::class, 'sendTemplate']);

        Route::get('contacts', [MobileInboxController::class, 'getContacts']);
        Route::post('contacts', [MobileInboxController::class, 'addContact']);
        Route::post('contacts/upload-vcf', [MobileInboxController::class, 'uploadVcf']);

        Route::post('ai/openai-key', [MobileInboxController::class, 'uploadOpenAIKey']);
        Route::get('ai/openai-key', [MobileInboxController::class, 'getOpenAIKey']);
        Route::post('ai/business-info', [MobileInboxController::class, 'uploadBusinessInformation']);
        Route::get('ai/business-info', [MobileInboxController::class, 'getBusinessInformation']);
        Route::post('ai/toggle-response', [MobileInboxController::class, 'toggleAIHumanResponse']);

        Route::get('user-roles', [MobileInboxController::class, 'getUserRoles']);
        Route::put('users/{user_id}', [MobileInboxController::class, 'updateInboxUser']);
        Route::delete('users/{user_id}', [MobileInboxController::class, 'deleteInboxUser']);

        Route::get('new-message-count', [MobileInboxController::class, 'getNewMessageCount']);
        Route::get('plan-status', [MobileInboxController::class, 'checkPlanStatus']);

        Route::post('upload-file', [MobileInboxController::class, 'uploadFile']);
        Route::post('process-query', [MobileInboxController::class, 'processQuery']);
        Route::get('storage-info', [MobileInboxController::class, 'getClientStorageInfo']);
        Route::get('api-usage', [MobileInboxController::class, 'getApiUsage']);
        Route::delete('clear-data', [MobileInboxController::class, 'clearClientData']);

        Route::get('provider-keys/models', [ProviderKeysController::class, 'getModels']);
        Route::post('provider-keys/validate', [ProviderKeysController::class, 'validateKey']);
        Route::get('provider-keys', [ProviderKeysController::class, 'index']);
        Route::post('provider-keys', [ProviderKeysController::class, 'store']);
        Route::put('provider-keys/{id}', [ProviderKeysController::class, 'update']);
        Route::delete('provider-keys/{id}', [ProviderKeysController::class, 'destroy']);

        Route::get('usage/summary', [ProviderKeysController::class, 'usageSummary']);
        Route::get('usage/timeline', [ProviderKeysController::class, 'usageTimeline']);
    });
});
