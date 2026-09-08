<?php

declare(strict_types=1);

use App\Domains\WhatsappFlow\Http\Controllers\WhatsappFlowBuilderController;
use App\Domains\WhatsappFlow\Http\Controllers\WhatsappFlowController;
use Illuminate\Support\Facades\Route;

Route::prefix('whatsapp-flows')->name('whatsapp-flows.')->group(function () {
    Route::get('/', [WhatsappFlowController::class, 'index'])->name('index');
    Route::get('/create', [WhatsappFlowController::class, 'create'])->name('create');
    Route::post('/', [WhatsappFlowController::class, 'store'])->name('store');
    Route::post('/import', [WhatsappFlowBuilderController::class, 'import'])->name('import');
    Route::post('/check-name', [WhatsappFlowController::class, 'checkName'])->name('check-name');

    Route::get('/{whatsappFlow}', [WhatsappFlowController::class, 'show'])->name('show');
    Route::get('/{whatsappFlow}/edit', [WhatsappFlowController::class, 'edit'])->name('edit');
    Route::put('/{whatsappFlow}', [WhatsappFlowController::class, 'update'])->name('update');
    Route::delete('/{whatsappFlow}', [WhatsappFlowController::class, 'destroy'])->name('destroy');
    Route::post('/{whatsappFlow}/publish', [WhatsappFlowController::class, 'publish'])->name('publish');
    Route::get('/{whatsappFlow}/preview', [WhatsappFlowController::class, 'preview'])->name('preview');
    Route::patch('/{whatsappFlow}/archive', [WhatsappFlowController::class, 'archive'])->name('archive');
    Route::post('/{whatsappFlow}/duplicate', [WhatsappFlowController::class, 'duplicate'])->name('duplicate');
    Route::get('/{whatsappFlow}/stats', [WhatsappFlowController::class, 'stats'])->name('stats');

    Route::get('/{whatsappFlow}/data', [WhatsappFlowBuilderController::class, 'getData'])->name('data');
    Route::put('/{whatsappFlow}/data', [WhatsappFlowBuilderController::class, 'saveData'])->name('data.save');
    Route::get('/{whatsappFlow}/export', [WhatsappFlowBuilderController::class, 'export'])->name('export');
});
