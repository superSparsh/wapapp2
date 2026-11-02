<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Billing\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\RechargeSubscriptionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class RechargeSubscriptionRequestController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.wallet-payments.index', array_merge(
            $request->query(),
            ['tab' => 'manual'],
        ));
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
