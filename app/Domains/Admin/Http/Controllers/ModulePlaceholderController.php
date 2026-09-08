<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ModulePlaceholderController extends Controller
{
    public function show(string $module): View
    {
        $titles = [
            'retention' => 'Customer Retention',
            'billing-audit' => 'Billing Audit',
            'whatsapp-health' => 'WhatsApp Health',
            'message-performance' => 'Message Performance',
            'wallet-recharges' => 'Wallet Recharges',
            'cloud-bills' => 'Alibaba CAMS Bills',
            'data-purge' => 'Expired Data Purge',
            'pricing' => 'Country Pricing',
            'razorpay' => 'Razorpay Subscriptions',
            'announcements' => 'Announcements',
            'settings' => 'Platform Settings',
        ];

        abort_unless(isset($titles[$module]), 404);

        return view('admin.modules.placeholder', [
            'title' => $titles[$module],
            'module' => $module,
        ]);
    }
}
