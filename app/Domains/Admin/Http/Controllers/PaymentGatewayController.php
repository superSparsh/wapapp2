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

        $payload = [
            'payment.razorpay_enabled' => $validated['payment_razorpay_enabled'] ?? '0',
            'payment.razorpay_key' => $validated['payment_razorpay_key'] ?? '',
            'payment.offline_instructions' => $validated['payment_offline_instructions'] ?? '',
        ];

        // Keep existing secrets when the fields are left blank on save.
        $secret = trim((string) ($validated['payment_razorpay_secret'] ?? ''));
        if ($secret !== '') {
            $payload['payment.razorpay_secret'] = $secret;
        }

        $webhookSecret = trim((string) ($validated['payment_razorpay_webhook_secret'] ?? ''));
        if ($webhookSecret !== '') {
            $payload['payment.razorpay_webhook_secret'] = $webhookSecret;
        }

        $this->settings->save($payload);

        return back()->with('status', 'Payment gateway settings saved.');
    }
}
