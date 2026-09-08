<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\BillingAuditService;
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
        $filters = $request->only(['q', 'tenant', 'type']);
        $report = $this->billing->audit($filters, (int) $request->integer('page', 1));

        return view('admin.billing-audit.index', [
            ...$report,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->billing->exportCsv($request->only(['q', 'tenant', 'type']));
    }
}
