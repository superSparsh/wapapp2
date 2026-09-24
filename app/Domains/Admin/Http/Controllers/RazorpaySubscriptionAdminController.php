<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\CrossTenantScanner;
use App\Domains\Admin\Support\AdminListQuery;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class RazorpaySubscriptionAdminController extends Controller
{
    public function __construct(
        private readonly CrossTenantScanner $scanner,
    ) {}

    public function index(Request $request): View
    {
        $parsed = AdminListQuery::fromRequest(
            $request,
            allowedSorts: ['starts_at', 'ends_at', 'amount', 'status', 'id'],
            defaultSort: 'starts_at',
            defaultDirection: 'desc',
        );
        $tenantId = trim((string) $request->query('tenant', ''));
        $rows = $this->scanner->map(function (): array {
            return Subscription::query()
                ->whereNotNull('razorpay_subscription_id')
                ->where('razorpay_subscription_id', '!=', '')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (Subscription $sub): array => [
                    'id' => $sub->id,
                    'razorpay_subscription_id' => (string) $sub->razorpay_subscription_id,
                    'status' => $sub->status?->value,
                    'amount' => (string) $sub->amount,
                    'currency' => (string) $sub->currency,
                    'starts_at' => optional($sub->starts_at)?->toDateTimeString(),
                    'ends_at' => optional($sub->ends_at)?->toDateTimeString(),
                ])->all();
        }, $tenantId !== '' ? $tenantId : null);

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 25;
        $sorted = AdminListQuery::sortRows(
            $rows,
            $parsed['sort'],
            $parsed['direction'],
            ['starts_at', 'ends_at', 'amount', 'status', 'id'],
            'starts_at',
        );
        $filters = [
            'tenant' => $tenantId,
            'sort' => $parsed['sort'],
            'direction' => $parsed['direction'],
        ];
        $paginator = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter($filters)],
        );

        return view('admin.razorpay.index', [
            'items' => $paginator,
            'filters' => $filters,
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
            'sortOptions' => [
                ['value' => 'starts_at', 'label' => 'Starts newest', 'direction' => 'desc'],
                ['value' => 'starts_at', 'label' => 'Starts oldest', 'direction' => 'asc'],
                ['value' => 'amount', 'label' => 'Highest amount', 'direction' => 'desc'],
                ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
            ],
        ]);
    }
}
