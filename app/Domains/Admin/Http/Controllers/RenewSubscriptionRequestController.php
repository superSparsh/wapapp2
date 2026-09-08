<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\CustomerAdminService;
use App\Http\Controllers\Controller;
use App\Models\RenewSubscriptionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RenewSubscriptionRequestController extends Controller
{
    public function __construct(
        private readonly CustomerAdminService $customers,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin.renew-requests.index', [
            'rows' => RenewSubscriptionRequest::query()
                ->with(['tenant:id,name,company_name', 'plan:id,name,currency,price'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
        ]);
    }

    public function approve(RenewSubscriptionRequest $renewRequest): RedirectResponse
    {
        if (! $renewRequest->isPending()) {
            return back()->with('error', 'This request is no longer pending.');
        }

        $tenant = $renewRequest->tenant;

        if ($tenant === null) {
            return back()->with('error', 'Customer no longer exists.');
        }

        $this->customers->assignPlan($tenant, $renewRequest->plan_id === null ? null : (int) $renewRequest->plan_id);

        $renewRequest->forceFill([
            'status' => RenewSubscriptionRequest::STATUS_APPROVED,
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ])->save();

        return back()->with('status', 'Renewal approved.');
    }
}
