<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\BillingAuditService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletRechargeController extends Controller
{
    public function __construct(
        private readonly BillingAuditService $billing,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'tenant_id']);
        $report = $this->billing->walletRecharges($filters, (int) $request->integer('page', 1));

        return view('admin.wallet-recharges.index', [
            ...$report,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
        ]);
    }
}
