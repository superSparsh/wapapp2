<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Services\NotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function markRead(NotificationService $notificationService): JsonResponse
    {
        $notificationService->markAsRead();

        return response()->json(['success' => true]);
    }
}
