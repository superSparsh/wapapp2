<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Support\AdminListQuery;
use App\Domains\Billing\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\RechargeSubscriptionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RechargeSubscriptionRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'status', 'amount', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        $query = RechargeSubscriptionRequest::query()
            ->with('tenant:id,name,company_name')
            ->when($status !== '', fn ($builder) => $builder->where('status', $status));

        AdminListQuery::applySearch($query, $parsed['q'], ['tenant_id', 'notes', 'status', 'currency']);
        AdminListQuery::applySort(
            $query,
            $parsed['sort'],
            $parsed['direction'],
            [
                'id' => 'id',
                'status' => 'status',
                'amount' => 'amount',
                'created_at' => 'created_at',
            ],
            'id',
        );

        return view('admin.recharge-requests.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'status' => $status,
            'filters' => $parsed,
            'sortOptions' => [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'amount', 'label' => 'Amount high–low', 'direction' => 'desc'],
                ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
                ['value' => 'created_at', 'label' => 'Requested', 'direction' => 'desc'],
            ],
        ]);
    }

    public function approve(RechargeSubscriptionRequest $rechargeRequest): RedirectResponse
    {
        if (! $rechargeRequest->isPending()) {
            return back()->with('error', 'This request is no longer pending.');
        }

        $tenant = $rechargeRequest->tenant;

        if ($tenant === null) {
            return back()->with('error', 'Customer no longer exists.');
        }

        $amount = (float) $rechargeRequest->amount;

        try {
            tenancy()->initialize($tenant);
            app(WalletService::class)->adminCredit(
                $amount,
                'Admin approved recharge request #'.$rechargeRequest->id,
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Unable to credit the customer wallet: '.$e->getMessage());
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        $rechargeRequest->forceFill([
            'status' => RechargeSubscriptionRequest::STATUS_APPROVED,
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ])->save();

        return back()->with('status', 'Recharge approved and wallet credited.');
    }
}
