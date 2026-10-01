<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

use App\Domains\Billing\Services\ServiceMessageFreeAllowanceService;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Dashboard\Services\DashboardService;
use App\Domains\MobileApi\Support\MobileWallet;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CountryPricing;
use App\Models\MailList;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Legacy-compatible mobile dashboard.
 * Top-level `data` values must all be Maps (Flutter Map.from on each key).
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly SubscriptionService $subscriptions,
        private readonly ServiceMessageFreeAllowanceService $freeService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $today = $this->safeCredits(DashboardService::PERIOD_DAILY);
            $week = $this->safeCredits(DashboardService::PERIOD_WEEKLY);
            $month = $this->safeCredits(DashboardService::PERIOD_MONTHLY);
            $free = $this->freeService->summary();

            $subscription = $this->subscriptions->subscriptionSummary();
            $walletBalance = MobileWallet::balance();
            $walletAmount = MobileWallet::amountString($walletBalance);
            [$costMarketing, $costUtility, $costService] = $this->conversationCosts();

            $lists = MailList::query()->orderByDesc('id')->limit(20)->get(['id', 'name']);
            $campaigns = Campaign::query()
                ->where('status', CampaignStatus::Completed)
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'uuid', 'name', 'status', 'created_at']);

            [$name, $uid] = $this->resolveUserLabel($user);
            $timezone = (string) (tenant()?->timezone ?: config('app.timezone', 'Asia/Kolkata'));

            $planName = $subscription['plan_name'] ?? $subscription['plan']?->name ?? null;
            $expiresAt = $subscription['expires_at'] ?? null;
            $remainingDays = null;
            $validUntil = null;
            // SubscriptionService returns Carbon\Carbon (not Illuminate\Support\Carbon).
            if ($expiresAt instanceof CarbonInterface) {
                $expires = Carbon::instance($expiresAt);
                $remainingDays = max(0, (int) floor((float) now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false)));
                $validUntil = $expires->toDateString();
                $expiresAt = $expires;
            }

            $dailyTierLimit = max(1, (int) ($today['marketing_limit'] ?? 1000));
            $monthlyTierLimit = $dailyTierLimit * 30;

            // Match web credits card: free service remaining this calendar month.
            $serviceFreeRemaining = max(0, (int) ($free['remaining'] ?? 0));

            $marketingToday = (int) ($today['marketing'] ?? 0);
            $utilityToday = (int) ($today['utility'] ?? 0);
            $serviceToday = (int) ($today['service'] ?? 0);
            $marketingWeek = (int) ($week['marketing'] ?? 0);
            $utilityWeek = (int) ($week['utility'] ?? 0);
            $serviceWeek = (int) ($week['service'] ?? 0);
            $marketingMonth = (int) ($month['marketing'] ?? 0);
            $utilityMonth = (int) ($month['utility'] ?? 0);
            $serviceMonth = (int) ($month['service'] ?? 0);

            $dashboardData = [
                'user_info' => [
                    'uid' => $uid,
                    'name' => $name !== '' ? $name : 'User',
                    'timezone' => $timezone,
                    'current_time' => now()->setTimezone($timezone)->toIso8601String(),
                    // Strings so Flutter double.tryParse works (JSON numbers often parse as 0).
                    'wallet_amount' => $walletAmount,
                    'wallet_balance' => $walletAmount,
                ],
                'subscription_info' => [
                    'plan_name' => $planName,
                    'remaining_days' => $remainingDays,
                    'valid_until' => $validUntil,
                    'expires_at' => $expiresAt instanceof CarbonInterface ? $expiresAt->toIso8601String() : null,
                    'plan_expires' => $validUntil,
                    'current_period_ends_at' => $validUntil,
                    'status' => $subscription['subscription']?->status?->value
                        ?? ($subscription['subscription'] !== null || filled($planName) ? 'active' : null),
                    'wallet_amount' => $walletAmount,
                    'wallet_balance' => $walletAmount,
                ],
                // Dedicated map so Flutter Map.from(data['wallet_info']) works and shows balance.
                'wallet_info' => [
                    'wallet_amount' => $walletAmount,
                    'wallet_balance' => $walletAmount,
                    'currency' => 'INR',
                    'amount' => $walletAmount,
                    'current_wallet_amount' => $walletAmount,
                ],
                'stats' => [
                    'today' => [
                        'marketing' => $marketingToday,
                        'utility' => $utilityToday,
                        'service' => $serviceToday,
                        // Mobile "Sent" must not include service conversations.
                        'total_delivered' => $marketingToday + $utilityToday,
                    ],
                    'last_7_days' => [
                        'marketing' => $marketingWeek,
                        'utility' => $utilityWeek,
                        'service' => $serviceWeek,
                        'total_delivered' => $marketingWeek + $utilityWeek,
                    ],
                    'last_30_days' => [
                        'marketing' => $marketingMonth,
                        'utility' => $utilityMonth,
                        'service' => $serviceMonth,
                        'total_delivered' => $marketingMonth + $utilityMonth,
                    ],
                ],
                'conversation_estimates' => [
                    'based_on_wallet_balance' => [
                        'daily_marketing' => max(0, (int) min(
                            floor($walletBalance / max(0.0001, $costMarketing)),
                            max(0, $dailyTierLimit - $marketingToday),
                        )),
                        'daily_utility' => max(0, (int) min(
                            floor($walletBalance / max(0.0001, $costUtility)),
                            max(0, $dailyTierLimit - $utilityToday),
                        )),
                        'monthly_marketing' => max(0, (int) min(
                            floor($walletBalance / max(0.0001, $costMarketing)),
                            max(0, $monthlyTierLimit - $marketingMonth),
                        )),
                        'monthly_utility' => max(0, (int) min(
                            floor($walletBalance / max(0.0001, $costUtility)),
                            max(0, $monthlyTierLimit - $utilityMonth),
                        )),
                        // Service estimate = free service remaining (web free-allowance), not wallet/0.57.
                        'service' => $serviceFreeRemaining,
                    ],
                ],
                'list_growth' => [
                    'available_lists' => $lists->map(fn (MailList $list): array => [
                        'name' => (string) $list->name,
                        'uid' => (string) ($list->uuid ?? $list->id),
                    ])->values()->all(),
                    'latest_list_stats' => $this->latestListStats($lists->first()),
                ],
                'recent_campaigns' => [
                    'available_campaigns' => $campaigns->map(fn (Campaign $campaign): array => [
                        'name' => (string) $campaign->name,
                        'uid' => (string) $campaign->uuid,
                    ])->values()->all(),
                    'latest_campaign_stats' => $this->latestCampaignStats($campaigns->first()),
                ],
            ];

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully.',
                'data' => $dashboardData,
            ]);
        } catch (Throwable $e) {
            Log::error('Mobile dashboard failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching dashboard data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float}
     */
    private function conversationCosts(): array
    {
        try {
            $pricing = CountryPricing::query()
                ->where('country_code', 'IN')
                ->first();
            if ($pricing !== null) {
                $m = (float) ($pricing->tekpro_marketing_price ?: $pricing->marketing_price ?: 0);
                $u = (float) ($pricing->tekpro_utility_price ?: $pricing->utility_price ?: 0);
                $s = (float) ($pricing->tekpro_service_price ?: $pricing->service_price ?: 0);
                if ($m > 0 && $u > 0 && $s > 0) {
                    return [$m, $u, $s];
                }
            }
        } catch (Throwable) {
            //
        }

        return [1.0, 0.58, 0.57];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeCredits(string $period): array
    {
        try {
            return $this->dashboard->creditsPayload($period)['credits'] ?? [];
        } catch (Throwable) {
            return [
                'marketing' => 0,
                'utility' => 0,
                'service' => 0,
                'marketing_limit' => 1000,
            ];
        }
    }

    private function resolveUserLabel(mixed $user): array
    {
        if ($user instanceof User) {
            $name = (string) ($user->name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')));

            return [$name, (string) ($user->uuid ?? $user->id)];
        }

        if ($user instanceof TeamMember) {
            return [
                trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                (string) ($user->uuid ?? $user->id),
            ];
        }

        return ['', ''];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestListStats(?MailList $list): ?array
    {
        if ($list === null) {
            return null;
        }

        return [
            'list_name' => (string) $list->name,
            'list_uid' => (string) ($list->uuid ?? $list->id),
            'growth' => [
                'dates' => [],
                'values' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestCampaignStats(?Campaign $campaign): ?array
    {
        if ($campaign === null) {
            return null;
        }

        return [
            'campaign_name' => (string) $campaign->name,
            'campaign_uid' => (string) $campaign->uuid,
            'stats' => [
                'sent' => 0,
                'delivered' => 0,
                'read' => 0,
                'replied' => 0,
                'failed' => 0,
            ],
        ];
    }
}
