<?php

use App\Domains\Admin\Http\Middleware\EnsureAdminIsActive;
use App\Domains\Admin\Http\Middleware\EnsureCustomerSiteAvailable;
use App\Domains\Admin\Services\ModuleErrorRecorder;
use App\Domains\Api\Http\Middleware\AuthenticateApiToken;
use App\Domains\Audience\Console\Commands\BackfillNonWhatsAppNumbersCommand;
use App\Domains\Audience\Console\Commands\VerifyListContactsCommand;
use App\Domains\Auth\Http\Middleware\EnsureEmailIsVerified;
use App\Domains\Auth\Http\Middleware\EnsureTwoFactorVerified;
use App\Domains\Auth\Http\Middleware\InitializeTenancyFromSession;
use App\Domains\Auth\Http\Middleware\RedirectIfAuthenticated;
use App\Domains\AutomationEvents\Console\Commands\ProcessAutomationEventsCommand;
use App\Domains\Billing\Console\Commands\CheckWalletAutoRechargeCommand;
use App\Domains\Billing\Console\Commands\ProcessSubscriptionRenewalsCommand;
use App\Domains\Billing\Console\Commands\ReconcileZohoWalletCommand;
use App\Domains\Billing\Console\Commands\SyncRazorpaySubscriptionsCommand;
use App\Domains\Campaigns\Console\Commands\ProcessDueCampaignsCommand;
use App\Domains\Drip\Console\Commands\ProcessDripAutomationsCommand;
use App\Domains\LegacyMigration\Console\ImportLegacyCountryPricingCommand;
use App\Domains\LegacyMigration\Console\ImportLegacyPlansCommand;
use App\Domains\LegacyMigration\Console\ImportLegacySettingsCommand;
use App\Domains\LegacyMigration\Console\LegacyDoctorCommand;
use App\Domains\LegacyMigration\Console\ListLegacyCustomersCommand;
use App\Domains\LegacyMigration\Console\KnowledgeBaseDoctorCommand;
use App\Domains\LegacyMigration\Console\BackfillLegacyAiBotUidsCommand;
use App\Domains\LegacyMigration\Console\MigrateLegacyCustomerCommand;
use App\Domains\LegacyMigration\Console\SyncDailyLegacyCommand;
use App\Domains\Operations\Console\Commands\DiscoverMetaPricingCsvUrlCommand;
use App\Domains\Operations\Console\Commands\ProcessInboundResponsesCommand;
use App\Domains\Operations\Console\Commands\ScheduleIntegrationSyncCommand;
use App\Domains\Operations\Console\Commands\SyncFreeUicQuotaCommand;
use App\Domains\Operations\Console\Commands\SyncMetaPricingCommand;
use App\Domains\Operations\Console\Commands\WhatsAppHealthDigestCommand;
use App\Domains\Operations\Console\Commands\WhatsAppHealthSnapshotCommand;
use App\Domains\Integration\Http\Middleware\EnsureWabaBound;
use App\Domains\Team\Http\Middleware\EnsureAccountOwner;
use App\Domains\Team\Http\Middleware\EnsureManager;
use App\Domains\Team\Http\Middleware\EnsureTeamPermission;
use App\Domains\Team\Http\Middleware\RedirectTeamMemberDashboard;
use App\Domains\Templates\Console\Commands\DeleteSoftDeletedTemplates;
use App\Domains\Templates\Console\Commands\SubmitPendingTemplates;
use App\Domains\Templates\Console\Commands\SyncTemplateStatuses;
use App\Domains\ThirdParty\Console\Commands\ProcessShopifyWebhooksCommand;
use App\Domains\Webhooks\Commands\RetryFailedDeliveriesCommand;
use App\Domains\WhatsappFlow\Console\Commands\CleanOldFlowSubmissions;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
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
            Route::middleware(['web', 'tenancy.session', 'auth:web,team', '2fa', 'team.owner', 'waba.bound'])
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
            'api/v1/message-uplink/alibaba',
            'api/v1/status-uplink/alibaba',
            'v1/message-uplink/alibaba',
            'v1/status-uplink/alibaba',
        ]);

        // Laravel's middleware priority sorts AuthenticateSession before appended
        // middleware unless we explicitly prepend tenancy initialization first.
        $middleware->prependToPriorityList(
            AuthenticatesSessions::class,
            InitializeTenancyFromSession::class,
        );

        $middleware->api(append: [
            EnsureCustomerSiteAvailable::class,
        ]);

        $middleware->web(
            remove: [
                AuthenticateSession::class,
            ],
            append: [
                InitializeTenancyFromSession::class,
                AuthenticateSession::class,
                EnsureCustomerSiteAvailable::class,
            ],
        );

        $middleware->alias([
            'api.token' => AuthenticateApiToken::class,
            'tenancy.session' => InitializeTenancyFromSession::class,
            '2fa' => EnsureTwoFactorVerified::class,
            'verified' => EnsureEmailIsVerified::class,
            'team.owner' => EnsureAccountOwner::class,
            'team.manager' => EnsureManager::class,
            'team.permission' => EnsureTeamPermission::class,
            'team.redirect-dashboard' => RedirectTeamMemberDashboard::class,
            'waba.bound' => EnsureWabaBound::class,
            'admin.active' => EnsureAdminIsActive::class,
            'guest' => RedirectIfAuthenticated::class,
            'maintenance.customer' => EnsureCustomerSiteAvailable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->ajax()
                || $request->expectsJson(),
        );

        $exceptions->reportable(function (Throwable $e): void {
            try {
                app(ModuleErrorRecorder::class)
                    ->recordException($e);
            } catch (Throwable) {
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
        WhatsAppHealthDigestCommand::class,
        SyncMetaPricingCommand::class,
        DiscoverMetaPricingCsvUrlCommand::class,
        SyncFreeUicQuotaCommand::class,
        VerifyListContactsCommand::class,
        BackfillNonWhatsAppNumbersCommand::class,
        SubmitPendingTemplates::class,
        SyncTemplateStatuses::class,
        DeleteSoftDeletedTemplates::class,
        RetryFailedDeliveriesCommand::class,
        CleanOldFlowSubmissions::class,
        ProcessShopifyWebhooksCommand::class,
        MigrateLegacyCustomerCommand::class,
        BackfillLegacyAiBotUidsCommand::class,
        KnowledgeBaseDoctorCommand::class,
        SyncDailyLegacyCommand::class,
        ListLegacyCustomersCommand::class,
        LegacyDoctorCommand::class,
        ImportLegacyPlansCommand::class,
        ImportLegacySettingsCommand::class,
        ImportLegacyCountryPricingCommand::class,
    ])
    ->create();
