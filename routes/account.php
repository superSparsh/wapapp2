<?php

declare(strict_types=1);

use App\Domains\Account\Http\Controllers\ActivityLogController;
use App\Domains\Account\Http\Controllers\ApiDocsController;
use App\Domains\Account\Http\Controllers\ApiTokenController;
use App\Domains\Account\Http\Controllers\DataDeletionController;
use App\Domains\Account\Http\Controllers\NotificationContactController;
use App\Domains\Account\Http\Controllers\NotificationController;
use App\Domains\Account\Http\Controllers\ProfileController;
use App\Domains\Account\Http\Controllers\SecurityController;
use App\Domains\Billing\Http\Controllers\SubscriptionController;
use App\Domains\Integration\Http\Controllers\IntegrationController;
use App\Domains\Integration\Http\Controllers\PhoneLineController;
use Illuminate\Support\Facades\Route;

Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');

Route::prefix('profile')->name('profile.')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('index');
    Route::post('/', [ProfileController::class, 'update'])->name('update');

    Route::get('/integration', [IntegrationController::class, 'index'])->name('integration');
    Route::get('/integration/connected/{whatsappLine?}', [IntegrationController::class, 'edit'])->name('integration.connected');
    Route::post('/integration/connected/{whatsappLine}', [IntegrationController::class, 'update'])->name('integration.update');
    Route::post('/integration/sync', [IntegrationController::class, 'sync'])->name('integration.sync');

    Route::prefix('phone-lines')->name('phone-lines.')->group(function () {
        Route::get('/', [PhoneLineController::class, 'index'])->name('index');
        Route::get('/add', [PhoneLineController::class, 'create'])->name('add');
        Route::post('/add', [PhoneLineController::class, 'store'])->name('add.store');
        Route::post('/password', [PhoneLineController::class, 'setPassword'])->name('password');
        Route::post('/login-as', [PhoneLineController::class, 'loginAs'])->name('login-as');
        Route::post('/exit-context', [PhoneLineController::class, 'exitContext'])->name('exit-context');
        Route::post('/set-default', [PhoneLineController::class, 'setDefault'])->name('set-default');
    });

    Route::get('/api', [ApiTokenController::class, 'show'])->name('api');
    Route::post('/api/renew', [ApiTokenController::class, 'renew'])->name('api.renew');
    Route::get('/api/docs', [ApiDocsController::class, 'show'])->name('api.docs');

    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription');
    Route::get('/subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');
    Route::post('/subscription/select-plan', [SubscriptionController::class, 'selectPlan'])->name('subscription.select-plan');
    Route::get('/subscription/billing', [SubscriptionController::class, 'billing'])->name('subscription.billing');
    Route::post('/subscription/billing', [SubscriptionController::class, 'saveBilling'])->name('subscription.billing.save');
    Route::get('/subscription/manage', [SubscriptionController::class, 'manage'])->name('subscription.manage');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::get('/subscription/payment', [SubscriptionController::class, 'payment'])->name('subscription.payment');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkoutSubscription'])->name('subscription.checkout');
    Route::post('/subscription/wallet-recharge', [SubscriptionController::class, 'walletRecharge'])->name('subscription.wallet-recharge');
    Route::post('/subscription/verify-payment', [SubscriptionController::class, 'verifyPayment'])->name('subscription.verify-payment');

    Route::get('/alerts', [NotificationContactController::class, 'show'])->name('alerts');
    Route::post('/alerts', [NotificationContactController::class, 'sync'])->name('alerts.sync');

    Route::get('/data-deletion', [DataDeletionController::class, 'show'])->name('data-deletion');
    Route::post('/data-deletion/export', [DataDeletionController::class, 'export'])->name('data-deletion.export');
    Route::post('/data-deletion/schedule', [DataDeletionController::class, 'schedule'])->name('data-deletion.schedule');
    Route::delete('/data-deletion/schedules/{schedule}', [DataDeletionController::class, 'cancelSchedule'])->name('data-deletion.cancel');
    Route::get('/data-deletion/exports/{export}/download', [DataDeletionController::class, 'download'])->name('data-deletion.download');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');

    Route::get('/security', [SecurityController::class, 'show'])->name('security');
    Route::post('/security/two-factor', [SecurityController::class, 'enable'])->name('security.enable');
    Route::post('/security/two-factor/disable', [SecurityController::class, 'disable'])->name('security.disable');
    Route::post('/security/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])->name('security.recovery');
});

Route::get('/account/security', fn () => redirect()->route('profile.security'))->name('account.security');
Route::get('/frontend/docs/api/v1', fn () => redirect()->route('profile.api.docs'))->name('api.docs.legacy');
