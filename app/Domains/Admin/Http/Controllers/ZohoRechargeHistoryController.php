<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZohoRechargeHistoryController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.wallet-payments.index', array_merge(
            $request->query(),
            ['tab' => 'zoho'],
        ));
    }

    public function show(ZohoWalletCreditRequest $creditRequest): View
    {
        return view('admin.zoho-credits.show', [
            'row' => $creditRequest->load('tenant:id,name,company_name'),
        ]);
    }
}
