<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
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
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'amount', 'wallet_credited_at', 'status', 'source'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        $query = ZohoWalletCreditRequest::query()
            ->with('tenant:id,name,company_name')
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->when($tenantId !== '', fn ($q) => $q->where('tenant_id', $tenantId));

        AdminListQuery::applySort($query, $parsed['sort'], $parsed['direction'], [
            'id' => 'id',
            'amount' => 'amount',
            'wallet_credited_at' => 'wallet_credited_at',
            'status' => 'status',
            'source' => 'source',
        ], 'id');

        return view('admin.zoho-credits.index', [
            'rows' => $query->paginate(25)->withQueryString(),
            'source' => $source,
            'sources' => self::SOURCES,
            'tenantId' => $tenantId,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'amount', 'label' => 'Highest amount', 'direction' => 'desc'],
                ['value' => 'wallet_credited_at', 'label' => 'Credited date', 'direction' => 'desc'],
                ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
                ['value' => 'source', 'label' => 'Source', 'direction' => 'asc'],
            ],
        ]);
    }

    public function show(ZohoWalletCreditRequest $creditRequest): View
    {
        return view('admin.zoho-credits.show', [
            'row' => $creditRequest->load('tenant:id,name,company_name'),
        ]);
    }
}
