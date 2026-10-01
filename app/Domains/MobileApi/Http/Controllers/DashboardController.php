<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\Billing\Services\ServiceMessageFreeAllowanceService;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Billing\Services\WalletService;
use App\Domains\Dashboard\Services\DashboardService;
use App\Domains\MobileApi\Services\MobileLineResolver;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly SubscriptionService $subscriptions,
        private readonly WalletService $wallet,
        private readonly ServiceMessageFreeAllowanceService $freeService,
        private readonly MobileLineResolver $lines,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = tenant();

        $today = $this->dashboard->creditsPayload(DashboardService::PERIOD_DAILY)['credits'];
        $week = $this->dashboard->creditsPayload(DashboardService::PERIOD_WEEKLY)['credits'];
        $month = $this->dashboard->creditsPayload(DashboardService::PERIOD_MONTHLY)['credits'];
        $free = $this->freeService->summary();

        $subscription = $this->subscriptions->subscriptionSummary();
        $walletBalance = round($this->wallet->balance(), 2);

        $lists = MailList::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name']);

        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Completed)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'uuid', 'name', 'status', 'created_at']);

        $latestList = $lists->first();
        $latestCampaign = $campaigns->first();

        $name = '';
        $uid = '';
        if ($user instanceof User) {
            $name = (string) ($user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));
            $uid = (string) ($user->uuid ?? $user->id);
        } elseif ($user instanceof TeamMember) {
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            $uid = (string) ($user->uuid ?? $user->id);
        }

        $timezone = (string) ($tenant?->timezone ?? config('app.timezone', 'Asia/Kolkata'));
        $planName = $subscription['plan_name'] ?? $subscription['plan']?->name ?? null;
        $expiresAt = $subscription['expires_at'] ?? null;
        $remainingDays = null;
        $validUntil = null;
        if ($expiresAt instanceof Carbon) {
            $remainingDays = (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);
            $validUntil = $expiresAt->toDateString();
        }

        $costMarketing = 1.0;
        $costUtility = 0.58;
        $costService = 0.57;
        $dailyLimit = max(1, (int) ($today['marketing_limit'] ?? 250));

        // Service card on mobile: free Meta-style service allowance (matches web wallet cards),
        // not raw outbound session message volume (that looked "wrong" vs web).
        $serviceToday = (int) ($today['service'] ?? 0);
        $serviceWeek = (int) ($week['service'] ?? 0);
        $serviceMonth = (int) ($free['used'] ?? $month['service'] ?? 0);
        $serviceLimit = (int) ($free['limit'] ?? 0);

        $dashboardData = [
            'user_info' => [
                'uid' => $uid,
                'name' => $name !== '' ? $name : 'User',
                'timezone' => $timezone,
                'current_time' => now()->setTimezone($timezone)->toIso8601String(),
                'wallet_amount' => $walletBalance,
                'wallet_balance' => $walletBalance,
            ],
            'subscription_info' => [
                'plan_name' => $planName,
                'remaining_days' => $remainingDays,
                'valid_until' => $validUntil,
                'expires_at' => $expiresAt instanceof Carbon ? $expiresAt->toIso8601String() : null,
                'status' => $subscription['subscription']?->status?->value
                    ?? ($subscription['subscription'] !== null || filled($planName) ? 'active' : null),
            ],
            'stats' => [
                'today' => [
                    'marketing' => (int) ($today['marketing'] ?? 0),
                    'utility' => (int) ($today['utility'] ?? 0),
                    'service' => $serviceToday,
                    // Legacy mobile UI treated total as marketing+utility (service shown separately).
                    'total_delivered' => (int) (($today['marketing'] ?? 0) + ($today['utility'] ?? 0)),
                ],
                'last_7_days' => [
                    'marketing' => (int) ($week['marketing'] ?? 0),
                    'utility' => (int) ($week['utility'] ?? 0),
                    'service' => $serviceWeek,
                    'total_delivered' => (int) (($week['marketing'] ?? 0) + ($week['utility'] ?? 0)),
                ],
                'last_30_days' => [
                    'marketing' => (int) ($month['marketing'] ?? 0),
                    'utility' => (int) ($month['utility'] ?? 0),
                    'service' => $serviceMonth,
                    'service_limit' => $serviceLimit > 0 ? $serviceLimit : null,
                    'service_remaining' => (int) ($free['remaining'] ?? 0),
                    'total_delivered' => (int) (($month['marketing'] ?? 0) + ($month['utility'] ?? 0)),
                ],
            ],
            'conversation_estimates' => [
                'based_on_wallet_balance' => [
                    'daily_marketing' => max(0, (int) min(floor($walletBalance / $costMarketing), $dailyLimit - (int) ($today['marketing'] ?? 0))),
                    'daily_utility' => max(0, (int) min(floor($walletBalance / $costUtility), $dailyLimit - (int) ($today['utility'] ?? 0))),
                    'monthly_marketing' => max(0, (int) min(floor($walletBalance / $costMarketing), ($dailyLimit * 30) - (int) ($month['marketing'] ?? 0))),
                    'monthly_utility' => max(0, (int) min(floor($walletBalance / $costUtility), ($dailyLimit * 30) - (int) ($month['utility'] ?? 0))),
                    'service' => max(0, (int) floor($walletBalance / $costService)),
                ],
            ],
            'list_growth' => [
                'available_lists' => $lists->map(fn (MailList $list): array => [
                    'name' => $list->name,
                    'uid' => (string) ($list->uuid ?? $list->id),
                ])->values()->all(),
                'latest_list_stats' => $latestList === null ? null : [
                    'list_name' => $latestList->name,
                    'list_uid' => (string) ($latestList->uuid ?? $latestList->id),
                    'growth' => [
                        'dates' => [],
                        'values' => [],
                    ],
                ],
            ],
            'recent_campaigns' => [
                'available_campaigns' => $campaigns->map(fn (Campaign $campaign): array => [
                    'name' => $campaign->name,
                    'uid' => $campaign->uuid,
                ])->values()->all(),
                'latest_campaign_stats' => $latestCampaign === null ? null : [
                    'campaign_name' => $latestCampaign->name,
                    'campaign_uid' => $latestCampaign->uuid,
                    'stats' => [
                        'sent' => 0,
                        'delivered' => 0,
                        'read' => 0,
                        'replied' => 0,
                        'failed' => 0,
                    ],
                ],
            ],
            // Top-level aliases — mobile clients read different keys historically.
            'wallet_amount' => $walletBalance,
            'wallet_balance' => $walletBalance,
            'valid_until' => $validUntil,
            'remaining_days' => $remainingDays,
            'service_free' => [
                'used' => (int) ($free['used'] ?? 0),
                'remaining' => (int) ($free['remaining'] ?? 0),
                'limit' => $serviceLimit,
                'per_line' => $free['per_line'] ?? [],
            ],
            'default_whatsapp_number' => $this->safeDefaultLinePhone(),
            'tenant_id' => $tenant?->id,
            'auth_guard' => Auth::getDefaultDriver(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully.',
            'data' => $dashboardData,
        ]);
    }

    private function safeDefaultLinePhone(): ?string
    {
        try {
            $phone = (string) $this->lines->resolve()->phone;

            return preg_replace('/\D+/', '', $phone) ?: $phone;
        } catch (\Throwable) {
            return null;
        }
    }
}
