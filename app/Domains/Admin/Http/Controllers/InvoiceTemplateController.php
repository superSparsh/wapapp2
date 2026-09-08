<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceTemplateController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.invoice-template.edit', [
            'template' => $this->settings->all()['invoice.custom_template'] ?? '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_custom_template' => ['nullable', 'string'],
        ]);

        $this->settings->save([
            'invoice.custom_template' => $validated['invoice_custom_template'] ?? '',
        ]);

        return back()->with('status', 'Invoice template saved.');
    }

    public function preview(): View
    {
        return view('admin.invoice-template.preview', [
            'template' => $this->settings->all()['invoice.custom_template'] ?? '',
        ]);
    }
}
