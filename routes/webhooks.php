<?php

declare(strict_types=1);

use App\Domains\ThirdParty\Http\Controllers\ShopifyWebhookController;
use App\Domains\Webhooks\Http\Controllers\AlibabaWebhookController;
use App\Domains\WhatsappFlow\Http\Controllers\FlowDataExchangeController;
use Illuminate\Support\Facades\Route;

$alibabaUplink = function (): void {
    Route::match(['get', 'post'], 'message-uplink/alibaba', [AlibabaWebhookController::class, 'message']);
    Route::match(['get', 'post'], 'status-uplink/alibaba', [AlibabaWebhookController::class, 'status']);
};

// CAMS / Alibaba console posts to /api/v1/...
Route::prefix('api/v1')->group(function (): void {
    Route::match(['get', 'post'], 'message-uplink/alibaba', [AlibabaWebhookController::class, 'message'])
        ->name('webhooks.alibaba.message');

    Route::match(['get', 'post'], 'status-uplink/alibaba', [AlibabaWebhookController::class, 'status'])
        ->name('webhooks.alibaba.status');

    Route::post('webhooks/shopify/{topic}', [ShopifyWebhookController::class, 'handle'])
        ->where('topic', '.*')
        ->name('webhooks.shopify.topic');
});

// Backward-compatible aliases without /api prefix
Route::prefix('v1')->group(function () use ($alibabaUplink): void {
    $alibabaUplink();

    Route::post('flow-exchange/{flowToken}', [FlowDataExchangeController::class, 'handle'])
        ->name('flow.exchange');

    Route::get('track/{token}.js', [\App\Domains\ThirdParty\Http\Controllers\WebsiteTrackingController::class, 'track'])
        ->name('trackers.script');
});

Route::post('webhooks/shopify/{topic}', [ShopifyWebhookController::class, 'handle'])
    ->where('topic', '.*')
    ->name('webhooks.shopify');
