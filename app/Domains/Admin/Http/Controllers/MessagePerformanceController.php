<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\WhatsappHealthAdminService;
use App\Domains\Admin\Support\AdminListQuery;
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
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['delivery_rate', 'read_rate', 'failed', 'tenant_name', 'sent'],
            defaultSort: 'delivery_rate',
            defaultDirection: 'desc',
        );
        $report = $this->health->messagePerformance(
            array_merge($request->only(['q', 'tenant']), [
                'sort' => $parsed['sort'],
                'direction' => $parsed['direction'],
            ]),
            (int) $request->integer('page', 1),
        );

        return view('admin.message-performance.index', [
            ...$report,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
            'sortOptions' => [
                ['value' => 'delivery_rate', 'label' => 'Delivery rate', 'direction' => 'desc'],
                ['value' => 'read_rate', 'label' => 'Read rate', 'direction' => 'desc'],
                ['value' => 'failed', 'label' => 'Most failed', 'direction' => 'desc'],
                ['value' => 'tenant_name', 'label' => 'Customer A–Z', 'direction' => 'asc'],
                ['value' => 'sent', 'label' => 'Most sent', 'direction' => 'desc'],
            ],
        ]);
    }
}
