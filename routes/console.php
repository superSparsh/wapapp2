<?php

use App\Console\Commands\ProcessDataDeletionSchedules;
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
use App\Domains\Templates\Console\Commands\DeleteSoftDeletedTemplates;
use App\Domains\Templates\Console\Commands\SubmitPendingTemplates;
use App\Domains\Templates\Console\Commands\SyncTemplateStatuses;
use App\Domains\Webhooks\Commands\RetryFailedDeliveriesCommand;
use App\Domains\ThirdParty\Console\Commands\ProcessShopifyWebhooksCommand;
use App\Domains\WhatsappFlow\Console\Commands\CleanOldFlowSubmissions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ProcessDataDeletionSchedules::class)->hourly();

// Template lifecycle
Schedule::command(SubmitPendingTemplates::class)->everyFiveMinutes();
Schedule::command(SyncTemplateStatuses::class)->everyTenMinutes();
// Daily: GetChatappTemplateDetail for coded templates — pick up Meta category changes
Schedule::command(SyncTemplateStatuses::class, ['--coded', '--limit=200'])->dailyAt('05:30');
Schedule::command(DeleteSoftDeletedTemplates::class)->everyFifteenMinutes();

// Campaign & automation execution
Schedule::command(ProcessDueCampaignsCommand::class)->everyMinute();
Schedule::command(ProcessDripAutomationsCommand::class)->everyMinute();
Schedule::command(ProcessShopifyWebhooksCommand::class)->everyMinute();
Schedule::command(ProcessAutomationEventsCommand::class)->dailyAt('02:00');
Schedule::command(ProcessInboundResponsesCommand::class)->everyMinute();

// Billing & wallet
Schedule::command(SyncRazorpaySubscriptionsCommand::class)->everyFiveMinutes();
Schedule::command(ProcessSubscriptionRenewalsCommand::class)->everyFiveMinutes();
Schedule::command(CheckWalletAutoRechargeCommand::class)->everyFiveMinutes();
Schedule::command(ReconcileZohoWalletCommand::class)->everyThirtyMinutes();

// Integrations sync
Schedule::command(ScheduleIntegrationSyncCommand::class)->everyFiveMinutes();

// Platform maintenance
Schedule::command(WhatsAppHealthSnapshotCommand::class)->dailyAt('01:00');
Schedule::command(SyncMetaPricingCommand::class)->dailyAt('03:00');
Schedule::command(SyncFreeUicQuotaCommand::class)->hourly();
Schedule::command(VerifyListContactsCommand::class)->dailyAt('04:00');
Schedule::command(CleanOldFlowSubmissions::class)->daily();
Schedule::command(RetryFailedDeliveriesCommand::class)->everyFifteenMinutes();
