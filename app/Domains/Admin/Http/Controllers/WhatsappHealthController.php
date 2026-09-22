<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\WhatsappHealthAdminService;
use App\Domains\Operations\Services\WhatsAppHealthDigestService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\WaHealthAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsappHealthController extends Controller
{
    public function __construct(
        private readonly WhatsappHealthAdminService $health,
        private readonly WhatsAppHealthDigestService $digest,
    ) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'fleet');
        $report = $this->health->fleet($request->only(['q', 'tenant', 'quality']), (int) $request->integer('page', 1));

        $alerts = null;
        $unreadAlerts = 0;
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        if (\Illuminate\Support\Facades\Schema::connection($central)->hasTable('wa_health_alerts')) {
            $unreadAlerts = WaHealthAlert::query()->where('is_read', false)->count();
            if ($tab === 'alerts') {
                $alerts = WaHealthAlert::query()
                    ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')->toString()))
                    ->when($request->boolean('unread_only'), fn ($q) => $q->where('is_read', false))
                    ->latest('occurred_at')
                    ->paginate(25)
                    ->withQueryString();
            }
        }

        return view('admin.whatsapp-health.index', [
            ...$report,
            'tab' => $tab,
            'alerts' => $alerts,
            'unreadAlerts' => $unreadAlerts,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
        ]);
    }

    public function markAlertRead(WaHealthAlert $alert): RedirectResponse
    {
        $alert->update(['is_read' => true]);

        return back()->with('status', 'Alert marked as read.');
    }

    public function markAllAlertsRead(): RedirectResponse
    {
        WaHealthAlert::query()->where('is_read', false)->update(['is_read' => true]);

        return back()->with('status', 'All alerts marked as read.');
    }

    public function sendDigest(): RedirectResponse
    {
        $this->digest->sendDailyDigest(force: true);

        return back()->with('status', 'WhatsApp Health digest emailed.');
    }
}
