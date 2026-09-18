<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceModeController extends Controller
{
    public function __construct(
        private readonly MaintenanceModeService $maintenance,
    ) {}

    public function edit(): View
    {
        return view('admin.maintenance.edit', [
            'state' => $this->maintenance->state(),
            'modules' => MaintenanceModeService::MODULES,
            'live' => $this->maintenance->enabled(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'in:0,1'],
            'message' => ['nullable', 'string', 'max:2000'],
            'until' => ['nullable', 'date'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['nullable', 'in:0,1'],
        ]);

        $modulePayload = [];
        foreach (array_keys(MaintenanceModeService::MODULES) as $key) {
            $modulePayload[$key] = (string) ($validated['modules'][$key] ?? '0') === '1';
        }

        $this->maintenance->save([
            'enabled' => (string) ($validated['enabled'] ?? '0') === '1',
            'message' => $validated['message'] ?? '',
            'until' => $validated['until'] ?? null,
            'modules' => $modulePayload,
        ]);

        $on = $this->maintenance->enabled();

        return back()->with(
            'status',
            $on
                ? 'Maintenance mode is ON. Customer dashboard access is locked; selected modules stay alive.'
                : 'Maintenance mode is OFF. Customer site is open again.',
        );
    }
}
