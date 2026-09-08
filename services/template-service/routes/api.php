<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\VariableController;
use App\Http\Middleware\AuthenticateServiceRequest;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([CorrelationIdMiddleware::class])->group(function () {
    Route::get('/health', HealthController::class);

    Route::middleware([AuthenticateServiceRequest::class, SetTenantContext::class])->group(function () {
        // Template Catalog & CRUD
        Route::get('/templates', [TemplateController::class, 'index']);
        Route::get('/templates/options', [TemplateController::class, 'options']);
        Route::post('/templates/draft', [TemplateController::class, 'draft']);
        Route::post('/templates/from-setup', [TemplateController::class, 'fromSetup']);
        Route::post('/templates/bulk-destroy', [TemplateController::class, 'bulkDestroy']);
        Route::get('/templates/preview/{code}', [TemplateController::class, 'preview']);
        Route::get('/templates/{uuid}', [TemplateController::class, 'show']);
        Route::put('/templates/{uuid}/step/{step}', [TemplateController::class, 'saveStep']);
        Route::post('/templates/{uuid}/submit', [TemplateController::class, 'submit']);
        Route::delete('/templates/{uuid}', [TemplateController::class, 'destroy']);

        // Variables
        Route::get('/variables', [VariableController::class, 'index']);
        Route::get('/variables/all', [VariableController::class, 'all']);
        Route::post('/variables', [VariableController::class, 'store']);
        Route::get('/variables/{uuid}', [VariableController::class, 'show']);
        Route::put('/variables/{uuid}', [VariableController::class, 'update']);
        Route::delete('/variables/{uuid}', [VariableController::class, 'destroy']);
    });
});
