<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\WhatsappHealthAdminService;
use App\Domains\Admin\Support\AdminListQuery;
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
        $fleetParsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['failed', 'delivered', 'read', 'phone', 'quality_rating', 'tenant_name'],
            defaultSort: 'failed',
            defaultDirection: 'desc',
        );
        $report = $this->health->fleet(
            array_merge($request->only(['q', 'tenant', 'quality']), [
                'sort' => $fleetParsed['sort'],
                'direction' => $fleetParsed['direction'],
            ]),
            (int) $request->integer('page', 1),
        );

        $alerts = null;
        $unreadAlerts = 0;
        $alertFilters = $fleetParsed;
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        if (\Illuminate\Support\Facades\Schema::connection($central)->hasTable('wa_health_alerts')) {
            $unreadAlerts = WaHealthAlert::query()->where('is_read', false)->count();
            if ($tab === 'alerts') {
                $alertFilters = AdminListQuery::fromRequest(
                    $request,
                    allowedSorts: ['occurred_at', 'severity', 'is_read'],
                    defaultSort: 'occurred_at',
                    defaultDirection: 'desc',
                );
                $query = WaHealthAlert::query()
                    ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')->toString()))
                    ->when($request->boolean('unread_only'), fn ($q) => $q->where('is_read', false));
                AdminListQuery::applySort($query, $alertFilters['sort'], $alertFilters['direction'], [
                    'occurred_at' => 'occurred_at',
                    'severity' => 'severity',
                    'is_read' => 'is_read',
                ], 'occurred_at');
                $alerts = $query->paginate(25)->withQueryString();
            }
        }

        return view('admin.whatsapp-health.index', [
            ...$report,
            'tab' => $tab,
            'alerts' => $alerts,
            'unreadAlerts' => $unreadAlerts,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
            'sortOptions' => $tab === 'alerts'
                ? [
                    ['value' => 'occurred_at', 'label' => 'Newest first', 'direction' => 'desc'],
                    ['value' => 'occurred_at', 'label' => 'Oldest first', 'direction' => 'asc'],
                    ['value' => 'severity', 'label' => 'Severity', 'direction' => 'desc'],
                    ['value' => 'is_read', 'label' => 'Unread first', 'direction' => 'asc'],
                ]
                : [
                    ['value' => 'failed', 'label' => 'Most failed', 'direction' => 'desc'],
                    ['value' => 'delivered', 'label' => 'Most delivered', 'direction' => 'desc'],
                    ['value' => 'read', 'label' => 'Most read', 'direction' => 'desc'],
                    ['value' => 'phone', 'label' => 'Phone A–Z', 'direction' => 'asc'],
                    ['value' => 'quality_rating', 'label' => 'Quality', 'direction' => 'asc'],
                    ['value' => 'tenant_name', 'label' => 'Customer A–Z', 'direction' => 'asc'],
                ],
            'filters' => array_merge($report['filters'], $tab === 'alerts' ? $alertFilters : []),
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
