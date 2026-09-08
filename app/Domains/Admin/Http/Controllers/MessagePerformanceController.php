<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\WhatsappHealthAdminService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessagePerformanceController extends Controller
{
    public function __construct(
        private readonly WhatsappHealthAdminService $health,
    ) {}

    public function index(Request $request): View
    {
        $report = $this->health->messagePerformance($request->only(['q', 'tenant']), (int) $request->integer('page', 1));

        return view('admin.message-performance.index', [
            ...$report,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
        ]);
    }
}
