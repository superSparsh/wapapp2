<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\AdminDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AdminDashboardService $dashboardService): View
    {
        return view('admin.dashboard', [
            'stats' => $dashboardService->stats(),
        ]);
    }
}
