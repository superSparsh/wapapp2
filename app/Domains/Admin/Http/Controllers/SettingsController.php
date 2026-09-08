<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'general_app_name' => ['nullable', 'string', 'max:191'],
            'general_support_email' => ['nullable', 'email', 'max:191'],
            'mailer_from_address' => ['nullable', 'email', 'max:191'],
            'mailer_from_name' => ['nullable', 'string', 'max:191'],
            'payment_razorpay_enabled' => ['nullable', 'in:0,1'],
            'payment_primary_gateway' => ['nullable', 'string', 'max:64'],
        ]);

        $this->settings->save([
            'general.app_name' => $validated['general_app_name'] ?? null,
            'general.support_email' => $validated['general_support_email'] ?? null,
            'mailer.from_address' => $validated['mailer_from_address'] ?? null,
            'mailer.from_name' => $validated['mailer_from_name'] ?? null,
            'payment.razorpay_enabled' => $validated['payment_razorpay_enabled'] ?? '0',
            'payment.primary_gateway' => $validated['payment_primary_gateway'] ?? null,
        ]);

        return back()->with('status', 'Settings saved.');
    }
}
