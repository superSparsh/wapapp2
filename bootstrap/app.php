<?php

use App\Domains\Auth\Http\Middleware\EnsureEmailIsVerified;
use App\Domains\Auth\Http\Middleware\EnsureTwoFactorVerified;
use App\Domains\Auth\Http\Middleware\InitializeTenancyFromSession;
use App\Domains\Team\Http\Middleware\EnsureAccountOwner;
use App\Domains\Team\Http\Middleware\EnsureManager;
use App\Domains\Team\Http\Middleware\EnsureTeamPermission;
use App\Domains\Team\Http\Middleware\RedirectTeamMemberDashboard;
use App\Domains\Audience\Console\Commands\VerifyListContactsCommand;
use App\Domains\AutomationEvents\Console\Commands\ProcessAutomationEventsCommand;
use App\Domains\Billing\Console\Commands\CheckWalletAutoRechargeCommand;
use App\Domains\Billing\Console\Commands\ProcessSubscriptionRenewalsCommand;
use App\Domains\Billing\Console\Commands\ReconcileZohoWalletCommand;
use App\Domains\Billing\Console\Commands\SyncRazorpaySubscriptionsCommand;
use App\Domains\Campaigns\Console\Commands\ProcessDueCampaignsCommand;
use App\Domains\Drip\Console\Commands\ProcessDripAutomationsCommand;
use App\Domains\Operations\Console\Commands\ProcessInboundResponsesCommand;
use App\Domains\Operations\Console\Commands\ScheduleIntegrationSyncCommand;
use App\Domains\Operations\Console\Commands\SyncFreeUicQuotaCommand;
use App\Domains\Operations\Console\Commands\SyncMetaPricingCommand;
use App\Domains\Operations\Console\Commands\WhatsAppHealthSnapshotCommand;
use App\Domains\ThirdParty\Console\Commands\ProcessShopifyWebhooksCommand;
use App\Domains\Templates\Console\Commands\DeleteSoftDeletedTemplates;
use App\Domains\Templates\Console\Commands\SubmitPendingTemplates;
use App\Domains\Templates\Console\Commands\SyncTemplateStatuses;
use App\Domains\Webhooks\Commands\RetryFailedDeliveriesCommand;
use App\Domains\WhatsappFlow\Console\Commands\CleanOldFlowSubmissions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require base_path('routes/webhooks.php');
            Route::middleware('web')->group(base_path('routes/auth.php'));
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware(['web', 'tenancy.session', 'auth:web,team', '2fa', 'verified', 'team.owner'])
                ->group(base_path('routes/account.php'));
        },
    )
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'middleware' => ['web', 'tenancy.session', 'auth:web,team'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('admin') || $request->is('admin/*')) {
                return route('admin.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            if ($request->is('admin') || $request->is('admin/*') || auth('admin')->check()) {
                return route('admin.dashboard');
            }

            return route('dashboard');
        });

        $middleware->validateCsrfTokens(except: [
            'lists/*/embedded-form-subscribe',
            'lists/*/embedded-form-subscribe-captcha',
            'api/v1/webhooks/shopify/*',
            'webhooks/shopify/*',
        ]);

        // Laravel's middleware priority sorts AuthenticateSession before appended
        // middleware unless we explicitly prepend tenancy initialization first.
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            InitializeTenancyFromSession::class,
        );

        $middleware->web(
            remove: [
                \Illuminate\Session\Middleware\AuthenticateSession::class,
            ],
            append: [
                InitializeTenancyFromSession::class,
                \Illuminate\Session\Middleware\AuthenticateSession::class,
            ],
        );

        $middleware->alias([
            'api.token' => \App\Domains\Api\Http\Middleware\AuthenticateApiToken::class,
            'tenancy.session' => InitializeTenancyFromSession::class,
            '2fa' => EnsureTwoFactorVerified::class,
            'verified' => EnsureEmailIsVerified::class,
            'team.owner' => EnsureAccountOwner::class,
            'team.manager' => EnsureManager::class,
            'team.permission' => EnsureTeamPermission::class,
            'team.redirect-dashboard' => RedirectTeamMemberDashboard::class,
            'admin.active' => \App\Domains\Admin\Http\Middleware\EnsureAdminIsActive::class,
            'guest' => \App\Domains\Auth\Http\Middleware\RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->reportable(function (\Throwable $e): void {
            try {
                app(\App\Domains\Admin\Services\ModuleErrorRecorder::class)
                    ->recordException($e);
            } catch (\Throwable) {
                //
            }
        });
    })
    ->withCommands([
        ProcessDueCampaignsCommand::class,
        ProcessDripAutomationsCommand::class,
        ProcessAutomationEventsCommand::class,
        CheckWalletAutoRechargeCommand::class,
        SyncRazorpaySubscriptionsCommand::class,
        ProcessSubscriptionRenewalsCommand::class,
        ReconcileZohoWalletCommand::class,
        ProcessInboundResponsesCommand::class,
        ScheduleIntegrationSyncCommand::class,
        WhatsAppHealthSnapshotCommand::class,
        SyncMetaPricingCommand::class,
        SyncFreeUicQuotaCommand::class,
        VerifyListContactsCommand::class,
        \App\Domains\Audience\Console\Commands\BackfillNonWhatsAppNumbersCommand::class,
        SubmitPendingTemplates::class,
        SyncTemplateStatuses::class,
        DeleteSoftDeletedTemplates::class,
        RetryFailedDeliveriesCommand::class,
        CleanOldFlowSubmissions::class,
        ProcessShopifyWebhooksCommand::class,
        \App\Domains\LegacyMigration\Console\MigrateLegacyCustomerCommand::class,
        \App\Domains\LegacyMigration\Console\ListLegacyCustomersCommand::class,
        \App\Domains\LegacyMigration\Console\LegacyDoctorCommand::class,
        \App\Domains\LegacyMigration\Console\ImportLegacyPlansCommand::class,
    ])
    ->create();
