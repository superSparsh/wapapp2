<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\RecentCampaignAdminService;
use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecentCampaignController extends Controller
{
    public function __construct(
        private readonly RecentCampaignAdminService $campaigns,
    ) {}

    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['activity_at', 'tenant_name', 'name', 'status', 'total_recipients', 'total_delivered', 'total_failed'],
            defaultSort: 'activity_at',
            defaultDirection: 'desc',
        );

        $report = $this->campaigns->list(
            array_merge($request->only(['q', 'tenant', 'status', 'date_from', 'date_to']), [
                'sort' => $parsed['sort'],
                'direction' => $parsed['direction'],
            ]),
            (int) $request->integer('page', 1),
        );

        return view('admin.recent-campaigns.index', [
            ...$report,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
            'statuses' => CampaignStatus::cases(),
            'sortOptions' => [
                ['value' => 'activity_at', 'label' => 'Most recent activity', 'direction' => 'desc'],
                ['value' => 'tenant_name', 'label' => 'Customer A–Z', 'direction' => 'asc'],
                ['value' => 'total_recipients', 'label' => 'Most recipients', 'direction' => 'desc'],
                ['value' => 'total_failed', 'label' => 'Most failed', 'direction' => 'desc'],
                ['value' => 'name', 'label' => 'Campaign name', 'direction' => 'asc'],
            ],
        ]);
    }

    public function toggle(Request $request, string $tenant, int $campaign): RedirectResponse
    {
        try {
            $updated = $this->campaigns->toggle($tenant, $campaign);
            $label = $updated->isPaused() ? 'paused' : 'resumed';

            return back()->with('status', "Campaign {$label} successfully.");
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function destroy(string $tenant, int $campaign): RedirectResponse
    {
        $this->campaigns->delete($tenant, $campaign);

        return back()->with('status', 'Campaign deleted successfully.');
    }
}
