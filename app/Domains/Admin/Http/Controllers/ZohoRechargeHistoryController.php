<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZohoRechargeHistoryController extends Controller
{
    /** @var list<string> */
    private const SOURCES = ['zoho', 'razorpay'];

    public function index(Request $request): View
    {
        $source = (string) $request->query('source', '');
        if (! in_array($source, self::SOURCES, true)) {
            $source = '';
        }

        $tenantId = (string) $request->query('tenant', '');

        return view('admin.zoho-credits.index', [
            'rows' => ZohoWalletCreditRequest::query()
                ->with('tenant:id,name,company_name')
                ->when($source !== '', fn ($query) => $query->where('source', $source))
                ->when($tenantId !== '', fn ($query) => $query->where('tenant_id', $tenantId))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'source' => $source,
            'sources' => self::SOURCES,
            'tenantId' => $tenantId,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
        ]);
    }

    public function show(ZohoWalletCreditRequest $creditRequest): View
    {
        return view('admin.zoho-credits.show', [
            'row' => $creditRequest->load('tenant:id,name,company_name'),
        ]);
    }
}
