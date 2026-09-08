<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Http\Controllers;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Dashboard\Services\DashboardService;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\User;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboardService): View
    {
        return view('dashboard.index', array_merge(
            $dashboardService->indexPayload($this->ownerUser()),
            $dashboardService->walletRechargePayload(),
        ));
    }

    public function credits(Request $request, DashboardService $dashboardService): JsonResponse
    {
        return response()->json($dashboardService->creditsPayload(
            $request->string('period')->toString(),
        ));
    }

    public function campaignReview(Request $request, DashboardService $dashboardService): JsonResponse
    {
        $campaign = PublicId::find(Campaign::class, (string) $request->input('campaign_id', ''));

        return response()->json($dashboardService->campaignReviewPayload($campaign));
    }

    public function wallet(Request $request, WalletService $walletService): View
    {
        $period = DashboardService::normalizePeriod(
            $request->string('period')->toString(),
            DashboardService::PERIOD_ALL,
        );

        return view('dashboard.wallet', [
            'transactions' => $walletService->paginateTransactions(
                search: $request->string('q')->toString(),
                perPage: (int) config('billing.wallet.history_per_page', 25),
                period: $period,
            ),
            'search' => $request->string('q')->toString(),
            'period' => $period,
        ]);
    }

    public function analytics(): View
    {
        return view('dashboard.analytics');
    }

    public function overview(): View
    {
        return view('dashboard.overview');
    }

    public function campaigns(): View
    {
        return view('dashboard.campaigns');
    }

    private function ownerUser(): User
    {
        $user = auth('web')->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
