<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\CrossTenantScanner;
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
        $sorted = $rows->sortByDesc('starts_at')->values();
        $paginator = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter(['tenant' => $tenantId])],
        );

        return view('admin.razorpay.index', [
            'items' => $paginator,
            'filters' => ['tenant' => $tenantId],
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']),
        ]);
    }
}
