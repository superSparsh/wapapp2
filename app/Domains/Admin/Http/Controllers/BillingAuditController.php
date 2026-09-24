<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\BillingAuditService;
use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingAuditController extends Controller
{
    public function __construct(
        private readonly BillingAuditService $billing,
    ) {}

    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['created_at', 'amount', 'id', 'source'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );
        $filters = array_merge($request->only(['q', 'tenant', 'type']), [
            'sort' => $parsed['sort'],
            'direction' => $parsed['direction'],
        ]);
        $report = $this->billing->audit($filters, (int) $request->integer('page', 1));

        return view('admin.billing-audit.index', [
            ...$report,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
            'sortOptions' => [
                ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
                ['value' => 'amount', 'label' => 'Highest amount', 'direction' => 'desc'],
                ['value' => 'source', 'label' => 'Source', 'direction' => 'asc'],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['created_at', 'amount', 'id', 'source'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        return $this->billing->exportCsv(array_merge(
            $request->only(['q', 'tenant', 'type']),
            ['sort' => $parsed['sort'], 'direction' => $parsed['direction']],
        ));
    }
}
