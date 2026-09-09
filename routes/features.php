<?php

declare(strict_types=1);

use App\Domains\Audience\Http\Controllers\BlacklistController;
use App\Domains\Audience\Http\Controllers\ContactBulkActionController;
use App\Domains\Audience\Http\Controllers\ContactController;
use App\Domains\Audience\Http\Controllers\ContactExportController;
use App\Domains\Audience\Http\Controllers\ContactImportController;
use App\Domains\Audience\Http\Controllers\ListFieldController;
use App\Domains\Audience\Http\Controllers\ListFormController;
use App\Domains\Audience\Http\Controllers\MailListController;
use App\Domains\Audience\Http\Controllers\SegmentController;
use App\Domains\Campaigns\Http\Controllers\CampaignActionsController;
use App\Domains\Campaigns\Http\Controllers\CampaignController;
use App\Domains\Campaigns\Http\Controllers\CampaignCreateController;
use App\Domains\Campaigns\Http\Controllers\CampaignListController;
use App\Domains\Campaigns\Http\Controllers\CampaignStatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware('team.permission:campaign_read')->prefix('campaigns')->name('campaigns.')->group(function () {
    Route::get('/', [CampaignController::class, 'index'])->name('index');
    Route::get('/active', [CampaignListController::class, 'active'])->name('active');
    Route::get('/scheduled', [CampaignListController::class, 'scheduled'])->name('scheduled');
    Route::get('/detail', fn () => view('campaigns.detail'))->name('detail');
    Route::post('/', [CampaignController::class, 'store'])->middleware('team.permission:campaign_write')->name('store');

    Route::prefix('create')->name('create.')->group(function () {
        Route::get('/', [CampaignCreateController::class, 'start'])
            ->middleware('team.permission:campaign_write')
            ->name('start');
        Route::get('/cancel', [CampaignCreateController::class, 'cancel'])
            ->middleware('team.permission:campaign_write')
            ->name('cancel');

        foreach (range(1, 6) as $legacyStep) {
            Route::get("/step-{$legacyStep}", fn () => redirect()->route('campaigns.create.step', $legacyStep))
                ->name("step-{$legacyStep}");
        }

        Route::get('/template-preview/{template}', [CampaignCreateController::class, 'templatePreview'])
            ->whereUuid('template')
            ->name('template-preview');
        Route::post('/test-message', [CampaignCreateController::class, 'testMessage'])
            ->middleware('team.permission:campaign_write')
            ->name('test-message');
        Route::post('/variables', [CampaignCreateController::class, 'saveVariables'])
            ->middleware('team.permission:campaign_write')
            ->name('variables.save');
        Route::post('/variables/import', [CampaignCreateController::class, 'importVariables'])
            ->middleware('team.permission:campaign_write')
            ->name('variables.import');
        Route::get('/step/{step}', [CampaignCreateController::class, 'showStep'])
            ->whereNumber('step')
            ->name('step');
        Route::post('/step/{step}', [CampaignCreateController::class, 'saveStep'])
            ->whereNumber('step')
            ->middleware('team.permission:campaign_write')
            ->name('save');
    });

    Route::get('/{bulkCampaign}/edit', [CampaignCreateController::class, 'edit'])
        ->middleware('team.permission:campaign_write')
        ->name('edit');
    Route::get('/{bulkCampaign}', [CampaignController::class, 'show'])->name('show');
    Route::patch('/{bulkCampaign}/toggle', [CampaignController::class, 'toggle'])->middleware('team.permission:campaign_write')->name('toggle');
    Route::post('/{bulkCampaign}/duplicate', [CampaignController::class, 'duplicate'])->middleware('team.permission:campaign_write')->name('duplicate');
    Route::post('/{bulkCampaign}/create-delivered-list', [CampaignController::class, 'createDeliveredList'])->middleware('team.permission:campaign_write')->name('create-delivered-list');
    Route::delete('/{bulkCampaign}', [CampaignController::class, 'destroy'])->middleware('team.permission:campaign_write')->name('destroy');

    Route::post('/{bulkCampaign}/test-message', [CampaignActionsController::class, 'testMessage'])->middleware('team.permission:campaign_write')->name('test-message');
    Route::post('/{bulkCampaign}/resend-failed', [CampaignActionsController::class, 'resendFailed'])->middleware('team.permission:campaign_write')->name('resend-failed');
    Route::get('/{bulkCampaign}/cost', [CampaignActionsController::class, 'calculateCost'])->name('cost');
    Route::post('/{bulkCampaign}/import-recipients', [CampaignActionsController::class, 'importRecipients'])->middleware('team.permission:campaign_write')->name('import-recipients');
    Route::post('/{bulkCampaign}/webhooks', [CampaignActionsController::class, 'storeWebhook'])->middleware('team.permission:campaign_write')->name('webhooks.store');

    Route::get('/{bulkCampaign}/statistics', [CampaignStatisticsController::class, 'overview'])->name('statistics');
    Route::get('/{bulkCampaign}/statistics/detail', [CampaignStatisticsController::class, 'detail'])->name('statistics.detail');
    Route::get('/{bulkCampaign}/statistics/export', [CampaignStatisticsController::class, 'export'])->name('statistics.export');
});

Route::middleware('team.permission:audience_read')->prefix('audience')->name('audience.')->group(function () {
    Route::get('/', [MailListController::class, 'index'])->name('index');
    Route::get('/overview', [MailListController::class, 'overview'])->name('overview');
    Route::get('/settings', [MailListController::class, 'settings'])->name('settings');

    Route::post('/lists', [MailListController::class, 'store'])->middleware('team.permission:audience_write')->name('lists.store');
    Route::put('/lists/{mailList}', [MailListController::class, 'update'])->middleware('team.permission:audience_write')->name('lists.update');
    Route::delete('/lists/{mailList}', [MailListController::class, 'destroy'])->middleware('team.permission:audience_write')->name('lists.destroy');
    Route::get('/lists/{mailList}/growth-chart', [MailListController::class, 'growthChart'])->name('lists.growth-chart');
    Route::get('/lists/{mailList}/statistics-chart', [MailListController::class, 'statisticsChart'])->name('lists.statistics-chart');

    Route::get('/subscribers', [ContactController::class, 'index'])->name('subscribers');
    Route::get('/subscribers/empty', [ContactController::class, 'empty'])->name('subscribers.empty');
    Route::get('/subscribers/detail', [ContactController::class, 'detail'])->name('subscribers.detail');
    Route::post('/subscribers', [ContactController::class, 'store'])->middleware('team.permission:audience_write')->name('subscribers.store');
    Route::put('/subscribers/{contact}', [ContactController::class, 'update'])->middleware('team.permission:audience_write')->name('subscribers.update');
    Route::delete('/subscribers/{contact}', [ContactController::class, 'destroy'])->middleware('team.permission:audience_write')->name('subscribers.destroy');
    Route::post('/subscribers/subscribe', [ContactController::class, 'subscribe'])->middleware('team.permission:audience_write')->name('subscribers.subscribe');
    Route::post('/subscribers/unsubscribe', [ContactController::class, 'unsubscribe'])->middleware('team.permission:audience_write')->name('subscribers.unsubscribe');
    Route::post('/subscribers/bulk-delete', [ContactController::class, 'bulkDelete'])->middleware('team.permission:audience_write')->name('subscribers.bulk-delete');
    Route::post('/subscribers/bulk-tags', [ContactBulkActionController::class, 'bulkTags'])->middleware('team.permission:audience_write')->name('subscribers.bulk-tags');
    Route::post('/subscribers/move', [ContactBulkActionController::class, 'move'])->middleware('team.permission:audience_write')->name('subscribers.move');
    Route::post('/subscribers/copy', [ContactBulkActionController::class, 'copy'])->middleware('team.permission:audience_write')->name('subscribers.copy');

    Route::get('/subscribers/import', [ContactImportController::class, 'show'])->name('subscribers.import');
    Route::post('/subscribers/import', [ContactImportController::class, 'store'])->middleware('team.permission:audience_write')->name('subscribers.import.store');
    Route::post('/subscribers/export', [ContactExportController::class, 'export'])->middleware('team.permission:audience_read')->name('subscribers.export');

    Route::get('/segments', [SegmentController::class, 'index'])->name('segments');
    Route::post('/segments', [SegmentController::class, 'store'])->middleware('team.permission:audience_write')->name('segments.store');
    Route::put('/segments/{segment}', [SegmentController::class, 'update'])->middleware('team.permission:audience_write')->name('segments.update');
    Route::delete('/segments/{segment}', [SegmentController::class, 'destroy'])->middleware('team.permission:audience_write')->name('segments.destroy');

    Route::get('/forms', [ListFormController::class, 'show'])->name('forms');
    Route::post('/forms', [ListFormController::class, 'update'])->middleware('team.permission:audience_write')->name('forms.update');
    Route::get('/list-fields', [ListFieldController::class, 'index'])->name('list-fields');
    Route::post('/list-fields', [ListFieldController::class, 'store'])->middleware('team.permission:audience_write')->name('list-fields.store');
    Route::put('/list-fields', [ListFieldController::class, 'update'])->middleware('team.permission:audience_write')->name('list-fields.update');
    Route::delete('/list-fields/{field}', [ListFieldController::class, 'destroy'])->middleware('team.permission:audience_write')->name('list-fields.destroy');

    Route::get('/blacklist', [BlacklistController::class, 'index'])->name('blacklist');
    Route::post('/blacklist', [BlacklistController::class, 'store'])->middleware('team.permission:audience_write')->name('blacklist.store');
    Route::delete('/blacklist/{blacklist}', [BlacklistController::class, 'destroy'])->middleware('team.permission:audience_write')->name('blacklist.destroy');
    Route::post('/blacklist/import', [BlacklistController::class, 'import'])->middleware('team.permission:audience_write')->name('blacklist.import');
});
