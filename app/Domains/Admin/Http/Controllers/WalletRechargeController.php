<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalletRechargeController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('admin.wallet-payments.index', array_merge(
            $request->query(),
            ['tab' => 'credits'],
        ));
    }
}
