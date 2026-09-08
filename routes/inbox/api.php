<?php

use Illuminate\Support\Facades\Route;
use App\Inbox\Http\Controllers\ConversationController;
use App\Inbox\Http\Controllers\MessageController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Conversations
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/', [ConversationController::class, 'store']);
        Route::get('/{id}', [ConversationController::class, 'show']);
        Route::put('/{id}', [ConversationController::class, 'update']);
        Route::delete('/{id}', [ConversationController::class, 'destroy']);
        Route::get('/{id}/messages', [ConversationController::class, 'showWithMessages']);
    });

    // Messages
    Route::prefix('messages')->group(function () {
        Route::get('/', [MessageController::class, 'index']);
        Route::post('/', [MessageController::class, 'store']);
        Route::get('/{id}', [MessageController::class, 'show']);
        Route::put('/{id}', [MessageController::class, 'update']);
        Route::delete('/{id}', [MessageController::class, 'destroy']);
        Route::patch('/{id}/read', [MessageController::class, 'markAsRead']);
    });

    // Unread messages count
    Route::get('/unread-count', function () {
        $user = auth()->user();
        $unreadCount = app(\App\Inbox\Services\ConversationService::class)->getUnreadCount($user->id);
        return response()->json(['unread_count' => $unreadCount]);
    });
});
