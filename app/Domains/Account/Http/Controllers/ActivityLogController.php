<?php

declare(strict_types=1);

namespace App\Domains\Account\Http\Controllers;

use App\Domains\Account\Services\ActivityLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request, ActivityLogService $activityLogService): View
    {
        $scope = $request->string('scope')->toString() ?: null;
        $perPage = (int) config('billing.activity_log.per_page', 25);

        return view('profile.activity-logs', [
            'logs' => $activityLogService->paginate($scope, $perPage),
            'scopes' => config('billing.activity_log.scopes', []),
            'actions' => ActivityLogService::ACTIONS,
            'activeScope' => $scope,
        ]);
    }
}
