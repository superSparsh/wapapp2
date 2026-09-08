<?php

declare(strict_types=1);

use App\Domains\Admin\Http\Controllers\AdminUserController;
use App\Domains\Admin\Http\Controllers\AnnouncementController;
use App\Domains\Admin\Http\Controllers\Auth\AdminLoginController;
use App\Domains\Admin\Http\Controllers\BillingAuditController;
use App\Domains\Admin\Http\Controllers\CloudBillController;
use App\Domains\Admin\Http\Controllers\CountryPricingController;
use App\Domains\Admin\Http\Controllers\CustomerController;
use App\Domains\Admin\Http\Controllers\DashboardController;
use App\Domains\Admin\Http\Controllers\DataPurgeController;
use App\Domains\Admin\Http\Controllers\ImpersonationController;
use App\Domains\Admin\Http\Controllers\MessagePerformanceController;
use App\Domains\Admin\Http\Controllers\PlanController;
use App\Domains\Admin\Http\Controllers\QueueController;
use App\Domains\Admin\Http\Controllers\RazorpaySubscriptionAdminController;
use App\Domains\Admin\Http\Controllers\RetentionController;
use App\Domains\Admin\Http\Controllers\SettingsController;
use App\Domains\Admin\Http\Controllers\WalletRechargeController;
use App\Domains\Admin\Http\Controllers\WhatsappHealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
    });

    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])
        ->name('impersonation.stop');

    Route::middleware(['auth:admin', 'admin.active'])->group(function (): void {
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{tenant}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/{tenant}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{tenant}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('/customers/{tenant}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
        Route::post('/customers/{tenant}/assign-plan', [CustomerController::class, 'assignPlan'])->name('customers.assign-plan');
        Route::post('/customers/{tenant}/extend-validity', [CustomerController::class, 'extendValidity'])->name('customers.extend-validity');
        Route::post('/customers/{tenant}/login-as', [CustomerController::class, 'loginAs'])->name('customers.login-as');

        Route::get('/retention', [RetentionController::class, 'index'])->name('retention.index');
        Route::get('/retention/export', [RetentionController::class, 'export'])->name('retention.export');
        Route::get('/retention/{tenant}', [RetentionController::class, 'show'])->name('retention.show');
        Route::post('/retention/{tenant}/notes', [RetentionController::class, 'storeNote'])->name('retention.notes.store');

        Route::get('/wallet-recharges', [WalletRechargeController::class, 'index'])->name('wallet-recharges.index');

        Route::get('/billing-audit', [BillingAuditController::class, 'index'])->name('billing-audit.index');
        Route::get('/billing-audit/export', [BillingAuditController::class, 'export'])->name('billing-audit.export');

        Route::get('/data-purge', [DataPurgeController::class, 'index'])->name('data-purge.index');
        Route::get('/data-purge/{tenant}', [DataPurgeController::class, 'show'])->name('data-purge.show');
        Route::post('/data-purge/{tenant}/mark', [DataPurgeController::class, 'mark'])->name('data-purge.mark');
        Route::post('/data-purge/{tenant}/unmark', [DataPurgeController::class, 'unmark'])->name('data-purge.unmark');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::post('/announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])->name('announcements.toggle');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/pricing', [CountryPricingController::class, 'index'])->name('pricing.index');
        Route::get('/pricing/create', [CountryPricingController::class, 'create'])->name('pricing.create');
        Route::post('/pricing', [CountryPricingController::class, 'store'])->name('pricing.store');
        Route::post('/pricing/import', [CountryPricingController::class, 'import'])->name('pricing.import');
        Route::get('/pricing/{pricing}/edit', [CountryPricingController::class, 'edit'])->name('pricing.edit');
        Route::put('/pricing/{pricing}', [CountryPricingController::class, 'update'])->name('pricing.update');
        Route::post('/pricing/{pricing}/toggle', [CountryPricingController::class, 'toggle'])->name('pricing.toggle');

        Route::get('/razorpay', [RazorpaySubscriptionAdminController::class, 'index'])->name('razorpay.index');

        Route::get('/cloud-bills', [CloudBillController::class, 'index'])->name('cloud-bills.index');
        Route::get('/cloud-bills/create', [CloudBillController::class, 'create'])->name('cloud-bills.create');
        Route::post('/cloud-bills', [CloudBillController::class, 'store'])->name('cloud-bills.store');
        Route::get('/cloud-bills/{bill}', [CloudBillController::class, 'show'])->name('cloud-bills.show');
        Route::put('/cloud-bills/{bill}', [CloudBillController::class, 'updateRate'])->name('cloud-bills.update');
        Route::get('/cloud-bills/{bill}/download', [CloudBillController::class, 'download'])->name('cloud-bills.download');
        Route::delete('/cloud-bills/{bill}', [CloudBillController::class, 'destroy'])->name('cloud-bills.destroy');

        Route::get('/whatsapp-health', [WhatsappHealthController::class, 'index'])->name('whatsapp-health.index');
        Route::get('/message-performance', [MessagePerformanceController::class, 'index'])->name('message-performance.index');

        Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::post('/plans/{plan}/toggle-status', [PlanController::class, 'toggleStatus'])->name('plans.toggle-status');

        Route::get('/queues', [QueueController::class, 'index'])->name('queues.index');
        Route::post('/queues/failed/retry-all', [QueueController::class, 'retryAll'])->name('queues.retry-all');
        Route::post('/queues/failed/flush', [QueueController::class, 'flush'])->name('queues.flush');
        Route::post('/queues/failed/{uuid}/retry', [QueueController::class, 'retry'])->name('queues.retry');
        Route::post('/queues/failed/{uuid}/forget', [QueueController::class, 'forget'])->name('queues.forget');

        Route::get('/admins', [AdminUserController::class, 'index'])->name('admins.index');
        Route::get('/admins/create', [AdminUserController::class, 'create'])->name('admins.create');
        Route::post('/admins', [AdminUserController::class, 'store'])->name('admins.store');
        Route::get('/admins/{admin}/edit', [AdminUserController::class, 'edit'])->name('admins.edit');
        Route::put('/admins/{admin}', [AdminUserController::class, 'update'])->name('admins.update');
        Route::post('/admins/{admin}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('admins.toggle-status');
    });
});
