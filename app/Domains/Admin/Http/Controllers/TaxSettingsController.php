<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.tax.edit', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tax_enabled' => ['nullable', 'in:0,1'],
            'tax_default_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_countries' => ['nullable', 'string', 'json'],
        ]);

        $this->settings->save([
            'tax.enabled' => $validated['tax_enabled'] ?? '0',
            'tax.default_rate' => $validated['tax_default_rate'] ?? '0',
            'tax.countries' => $validated['tax_countries'] ?? '',
        ]);

        return back()->with('status', 'Tax settings saved.');
    }
}
