<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\PlatformSettingsService;
use App\Domains\Admin\Services\TittuGatewayStatusService;
use App\Domains\Admin\Services\TittuGatewayUsageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TittuGatewayStatusController extends Controller
{
    public function __construct(
        private readonly TittuGatewayStatusService $status,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function index(Request $request, TittuGatewayUsageService $usage): View
    {
        $tab = $request->query('tab') === 'customers' ? 'customers' : 'status';

        return view('admin.tittu-gateway.index', [
            'tab' => $tab,
            'snapshot' => $tab === 'status' ? $this->status->snapshot() : null,
            'usage' => $tab === 'customers' ? $usage->report() : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status_token' => ['nullable', 'string', 'max:2000'],
            'clear_status_token' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('clear_status_token')) {
            $this->settings->save(['tittu_gateway.status_token' => '']);
        } elseif (filled($validated['status_token'] ?? null)) {
            $this->settings->save([
                'tittu_gateway.status_token' => trim((string) $validated['status_token']),
            ]);
        }

        return back()->with('status', 'Tittu Gateway token updated.');
    }
}
