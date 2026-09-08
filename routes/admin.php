<?php

declare(strict_types=1);

use App\Domains\Admin\Http\Controllers\AdminRoleController;
use App\Domains\Admin\Http\Controllers\AdminUserController;
use App\Domains\Admin\Http\Controllers\AnnouncementController;
use App\Domains\Admin\Http\Controllers\Auth\AdminLoginController;
use App\Domains\Admin\Http\Controllers\BillingAuditController;
use App\Domains\Admin\Http\Controllers\CloudBillController;
use App\Domains\Admin\Http\Controllers\CountryPricingController;
use App\Domains\Admin\Http\Controllers\CurrencyController;
use App\Domains\Admin\Http\Controllers\CustomerController;
use App\Domains\Admin\Http\Controllers\CustomerSubmissionController;
use App\Domains\Admin\Http\Controllers\DashboardController;
use App\Domains\Admin\Http\Controllers\DataPurgeController;
use App\Domains\Admin\Http\Controllers\EnterAdminViewController;
use App\Domains\Admin\Http\Controllers\FormTemplateController;
use App\Domains\Admin\Http\Controllers\ImpersonationController;
use App\Domains\Admin\Http\Controllers\InvoiceTemplateController;
use App\Domains\Admin\Http\Controllers\LanguageController;
use App\Domains\Admin\Http\Controllers\MessagePerformanceController;
use App\Domains\Admin\Http\Controllers\OAuthSettingsController;
use App\Domains\Admin\Http\Controllers\PageLayoutController;
use App\Domains\Admin\Http\Controllers\PaymentGatewayController;
use App\Domains\Admin\Http\Controllers\PlanController;
use App\Domains\Admin\Http\Controllers\PlatformTemplateController;
use App\Domains\Admin\Http\Controllers\PluginController;
use App\Domains\Admin\Http\Controllers\QueueController;
use App\Domains\Admin\Http\Controllers\RazorpaySubscriptionAdminController;
use App\Domains\Admin\Http\Controllers\RechargeSubscriptionRequestController;
use App\Domains\Admin\Http\Controllers\RenewSubscriptionRequestController;
use App\Domains\Admin\Http\Controllers\RetentionController;
use App\Domains\Admin\Http\Controllers\SettingsController;
use App\Domains\Admin\Http\Controllers\TaxSettingsController;
use App\Domains\Admin\Http\Controllers\WalletRechargeController;
use App\Domains\Admin\Http\Controllers\WhatsappHealthController;
use App\Domains\Admin\Http\Controllers\ZohoRechargeHistoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest:admin')->group(function (): void {
        Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->name('login.store');
    });

    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])
        ->name('impersonation.stop');

    Route::match(['get', 'post'], '/enter-from-app', EnterAdminViewController::class)
        ->middleware(['tenancy.session', 'auth:web,team'])
        ->name('enter-from-app');

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

        Route::get('/renew-requests', [RenewSubscriptionRequestController::class, 'index'])->name('renew-requests.index');
        Route::post('/renew-requests/{renewRequest}/approve', [RenewSubscriptionRequestController::class, 'approve'])->name('renew-requests.approve');

        Route::get('/recharge-requests', [RechargeSubscriptionRequestController::class, 'index'])->name('recharge-requests.index');
        Route::post('/recharge-requests/{rechargeRequest}/approve', [RechargeSubscriptionRequestController::class, 'approve'])->name('recharge-requests.approve');

        Route::get('/submissions', [CustomerSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('/submissions/readiness/{submission}', [CustomerSubmissionController::class, 'showReadiness'])->name('submissions.readiness.show');
        Route::get('/submissions/onboarding/{submission}', [CustomerSubmissionController::class, 'showOnboarding'])->name('submissions.onboarding.show');
        Route::post('/submissions/onboarding/{submission}/resend', [CustomerSubmissionController::class, 'resend'])->name('submissions.onboarding.resend');

        Route::get('/zoho-credits', [ZohoRechargeHistoryController::class, 'index'])->name('zoho-credits.index');
        Route::get('/zoho-credits/{creditRequest}', [ZohoRechargeHistoryController::class, 'show'])->name('zoho-credits.show');

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

        Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::get('/currencies/create', [CurrencyController::class, 'create'])->name('currencies.create');
        Route::post('/currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        Route::get('/currencies/{currency}/edit', [CurrencyController::class, 'edit'])->name('currencies.edit');
        Route::put('/currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
        Route::post('/currencies/{currency}/toggle', [CurrencyController::class, 'toggle'])->name('currencies.toggle');

        Route::get('/tax', [TaxSettingsController::class, 'edit'])->name('tax.edit');
        Route::put('/tax', [TaxSettingsController::class, 'update'])->name('tax.update');

        Route::get('/invoice-template', [InvoiceTemplateController::class, 'edit'])->name('invoice-template.edit');
        Route::put('/invoice-template', [InvoiceTemplateController::class, 'update'])->name('invoice-template.update');
        Route::get('/invoice-template/preview', [InvoiceTemplateController::class, 'preview'])->name('invoice-template.preview');

        Route::get('/payment-gateways', [PaymentGatewayController::class, 'edit'])->name('payment-gateways.edit');
        Route::put('/payment-gateways', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');

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

        Route::get('/admin-roles', [AdminRoleController::class, 'index'])->name('admin-roles.index');
        Route::get('/admin-roles/create', [AdminRoleController::class, 'create'])->name('admin-roles.create');
        Route::post('/admin-roles', [AdminRoleController::class, 'store'])->name('admin-roles.store');
        Route::get('/admin-roles/{role}/edit', [AdminRoleController::class, 'edit'])->name('admin-roles.edit');
        Route::put('/admin-roles/{role}', [AdminRoleController::class, 'update'])->name('admin-roles.update');
        Route::delete('/admin-roles/{role}', [AdminRoleController::class, 'destroy'])->name('admin-roles.destroy');

        Route::get('/oauth', [OAuthSettingsController::class, 'edit'])->name('oauth.edit');
        Route::put('/oauth', [OAuthSettingsController::class, 'update'])->name('oauth.update');

        Route::get('/platform-templates', [PlatformTemplateController::class, 'index'])->name('platform-templates.index');
        Route::get('/platform-templates/create', [PlatformTemplateController::class, 'create'])->name('platform-templates.create');
        Route::post('/platform-templates', [PlatformTemplateController::class, 'store'])->name('platform-templates.store');
        Route::get('/platform-templates/{template}/edit', [PlatformTemplateController::class, 'edit'])->name('platform-templates.edit');
        Route::put('/platform-templates/{template}', [PlatformTemplateController::class, 'update'])->name('platform-templates.update');
        Route::delete('/platform-templates/{template}', [PlatformTemplateController::class, 'destroy'])->name('platform-templates.destroy');

        Route::get('/form-templates', [FormTemplateController::class, 'index'])->name('form-templates.index');
        Route::get('/form-templates/create', [FormTemplateController::class, 'create'])->name('form-templates.create');
        Route::post('/form-templates', [FormTemplateController::class, 'store'])->name('form-templates.store');
        Route::get('/form-templates/{formTemplate}/edit', [FormTemplateController::class, 'edit'])->name('form-templates.edit');
        Route::put('/form-templates/{formTemplate}', [FormTemplateController::class, 'update'])->name('form-templates.update');
        Route::delete('/form-templates/{formTemplate}', [FormTemplateController::class, 'destroy'])->name('form-templates.destroy');

        Route::get('/page-layouts', [PageLayoutController::class, 'index'])->name('page-layouts.index');
        Route::get('/page-layouts/create', [PageLayoutController::class, 'create'])->name('page-layouts.create');
        Route::post('/page-layouts', [PageLayoutController::class, 'store'])->name('page-layouts.store');
        Route::get('/page-layouts/{pageLayout}/edit', [PageLayoutController::class, 'edit'])->name('page-layouts.edit');
        Route::put('/page-layouts/{pageLayout}', [PageLayoutController::class, 'update'])->name('page-layouts.update');
        Route::delete('/page-layouts/{pageLayout}', [PageLayoutController::class, 'destroy'])->name('page-layouts.destroy');

        Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
        Route::get('/languages/create', [LanguageController::class, 'create'])->name('languages.create');
        Route::post('/languages', [LanguageController::class, 'store'])->name('languages.store');
        Route::get('/languages/{language}/edit', [LanguageController::class, 'edit'])->name('languages.edit');
        Route::put('/languages/{language}', [LanguageController::class, 'update'])->name('languages.update');
        Route::post('/languages/{language}/toggle', [LanguageController::class, 'toggle'])->name('languages.toggle');
        Route::delete('/languages/{language}', [LanguageController::class, 'destroy'])->name('languages.destroy');

        Route::get('/plugins', [PluginController::class, 'index'])->name('plugins.index');
        Route::post('/plugins', [PluginController::class, 'store'])->name('plugins.store');
        Route::post('/plugins/{plugin}/toggle', [PluginController::class, 'toggle'])->name('plugins.toggle');
        Route::delete('/plugins/{plugin}', [PluginController::class, 'destroy'])->name('plugins.destroy');
    });
});
