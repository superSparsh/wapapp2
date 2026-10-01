<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Http\Controllers;

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
use App\Models\WalletAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Legacy-compatible mobile dashboard.
 * Response `data` must contain ONLY the six map keys the Flutter app casts with Map.from().
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly SubscriptionService $subscriptions,
        private readonly WalletService $wallet,
        private readonly MobileLineResolver $lines,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $today = $this->safeCredits(DashboardService::PERIOD_DAILY);
            $week = $this->safeCredits(DashboardService::PERIOD_WEEKLY);
            $month = $this->safeCredits(DashboardService::PERIOD_MONTHLY);

            $subscription = $this->subscriptions->subscriptionSummary();
            $walletBalance = $this->resolveWalletBalance();

            $lists = MailList::query()
                ->orderByDesc('id')
                ->limit(20)
                ->get(['id', 'name']);

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
            if ($expiresAt instanceof Carbon) {
                $remainingDays = (int) floor((float) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false));
                $validUntil = $expiresAt->toDateString();
            }

            $costMarketing = 1.0;
            $costUtility = 0.58;
            $costService = 0.57;
            $dailyTierLimit = max(1, (int) ($today['marketing_limit'] ?? 1000));

            // Exact legacy key set — do not add top-level list/scalar keys (Flutter Map.from crash).
            $dashboardData = [
                'user_info' => [
                    'uid' => $uid,
                    'name' => $name !== '' ? $name : 'User',
                    'timezone' => $timezone,
                    'current_time' => now()->setTimezone($timezone)->toIso8601String(),
                    // Nested inside user_info map (safe for Map.from parsers)
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
                        'service' => (int) ($today['service'] ?? 0),
                        'total_delivered' => (int) (
                            ($today['marketing'] ?? 0) + ($today['utility'] ?? 0) + ($today['service'] ?? 0)
                        ),
                    ],
                    'last_7_days' => [
                        'marketing' => (int) ($week['marketing'] ?? 0),
                        'utility' => (int) ($week['utility'] ?? 0),
                        'service' => (int) ($week['service'] ?? 0),
                        'total_delivered' => (int) (
                            ($week['marketing'] ?? 0) + ($week['utility'] ?? 0) + ($week['service'] ?? 0)
                        ),
                    ],
                    'last_30_days' => [
                        'marketing' => (int) ($month['marketing'] ?? 0),
                        'utility' => (int) ($month['utility'] ?? 0),
                        'service' => (int) ($month['service'] ?? 0),
                        'total_delivered' => (int) (
                            ($month['marketing'] ?? 0) + ($month['utility'] ?? 0) + ($month['service'] ?? 0)
                        ),
                    ],
                ],
                'conversation_estimates' => [
                    'based_on_wallet_balance' => [
                        'daily_marketing' => max(0, (int) min(floor($walletBalance / $costMarketing), $dailyTierLimit - (int) ($today['marketing'] ?? 0))),
                        'daily_utility' => max(0, (int) min(floor($walletBalance / $costUtility), $dailyTierLimit - (int) ($today['utility'] ?? 0))),
                        'monthly_marketing' => max(0, (int) min(floor($walletBalance / $costMarketing), ($dailyTierLimit * 30) - (int) ($month['marketing'] ?? 0))),
                        'monthly_utility' => max(0, (int) min(floor($walletBalance / $costUtility), ($dailyTierLimit * 30) - (int) ($month['utility'] ?? 0))),
                        'service' => max(0, (int) floor($walletBalance / $costService)),
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

    private function resolveWalletBalance(): float
    {
        try {
            $balance = round($this->wallet->balance(), 2);
            if ($balance > 0) {
                return $balance;
            }
        } catch (Throwable) {
            //
        }

        return round((float) (WalletAccount::query()->value('balance') ?? 0), 2);
    }

    /**
     * @return array{0: string, 1: string}
     */
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
            // Must stay a Map with dates/values lists (legacy getGrowthChartData shape).
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
