<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.payment-gateways.edit', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_razorpay_enabled' => ['nullable', 'in:0,1'],
            'payment_razorpay_key' => ['nullable', 'string', 'max:191'],
            'payment_razorpay_secret' => ['nullable', 'string', 'max:191'],
            'payment_razorpay_webhook_secret' => ['nullable', 'string', 'max:191'],
            'payment_offline_instructions' => ['nullable', 'string'],
        ]);

        $this->settings->save([
            'payment.razorpay_enabled' => $validated['payment_razorpay_enabled'] ?? '0',
            'payment.razorpay_key' => $validated['payment_razorpay_key'] ?? '',
            'payment.razorpay_secret' => $validated['payment_razorpay_secret'] ?? '',
            'payment.razorpay_webhook_secret' => $validated['payment_razorpay_webhook_secret'] ?? '',
            'payment.offline_instructions' => $validated['payment_offline_instructions'] ?? '',
        ]);

        return back()->with('status', 'Payment gateway settings saved.');
    }
}
