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
use App\Models\WalletAccount;
use App\Models\WhatsappLine;
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

        $latestList = $lists->first();
        $latestCampaign = $campaigns->first();

        [$name, $uid] = $this->resolveUserLabel($user);

        $timezone = (string) ($tenant?->timezone ?? config('app.timezone', 'Asia/Kolkata'));
        $planName = $subscription['plan_name'] ?? $subscription['plan']?->name ?? null;
        $expiresAt = $subscription['expires_at'] ?? null;
        [$remainingDays, $validUntil] = $this->resolveValidity($expiresAt);

        $costMarketing = 1.0;
        $costUtility = 0.58;
        $costService = 0.57;

        // Daily messaging-tier cap (same as web marketing card denominator for "today").
        $dailyTierLimit = max(1, (int) ($today['marketing_limit'] ?? 1000));

        // Service card: use DEFAULT LINE free allowance only (1000), not sum across every
        // migrated WhatsApp line (that produced bogus values like 6/29.6K).
        $defaultLineId = (int) (WhatsappLine::query()->where('is_default', true)->value('id')
            ?? WhatsappLine::query()->value('id')
            ?? 0);
        $perLine = collect($free['per_line'] ?? []);
        $defaultFree = $perLine->firstWhere('whatsapp_line_id', $defaultLineId)
            ?? $perLine->first();
        $serviceUsed = (int) ($defaultFree['used'] ?? $free['used'] ?? 0);
        $serviceLimit = (int) ($defaultFree['limit'] ?? $free['limit_per_line'] ?? 1000);

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
                'plan_expires' => $validUntil,
                'current_period_ends_at' => $validUntil,
                'status' => $subscription['subscription']?->status?->value
                    ?? ($subscription['subscription'] !== null || filled($planName) ? 'active' : null),
            ],
            'stats' => [
                'today' => [
                    'marketing' => (int) ($today['marketing'] ?? 0),
                    'utility' => (int) ($today['utility'] ?? 0),
                    'service' => (int) ($today['service'] ?? 0),
                    'marketing_limit' => $dailyTierLimit,
                    'utility_limit' => $dailyTierLimit,
                    'service_limit' => $serviceLimit,
                    'total_delivered' => (int) (($today['marketing'] ?? 0) + ($today['utility'] ?? 0)),
                ],
                'last_7_days' => [
                    'marketing' => (int) ($week['marketing'] ?? 0),
                    'utility' => (int) ($week['utility'] ?? 0),
                    'service' => (int) ($week['service'] ?? 0),
                    'marketing_limit' => $dailyTierLimit * 7,
                    'utility_limit' => $dailyTierLimit * 7,
                    'service_limit' => $serviceLimit,
                    'total_delivered' => (int) (($week['marketing'] ?? 0) + ($week['utility'] ?? 0)),
                ],
                'last_30_days' => [
                    'marketing' => (int) ($month['marketing'] ?? 0),
                    'utility' => (int) ($month['utility'] ?? 0),
                    // Mobile "Service Conversations" card reads these as used/limit.
                    'service' => $serviceUsed,
                    'marketing_limit' => $dailyTierLimit * 30,
                    'utility_limit' => $dailyTierLimit * 30,
                    'service_limit' => $serviceLimit,
                    'service_remaining' => max(0, $serviceLimit - $serviceUsed),
                    'total_delivered' => (int) (($month['marketing'] ?? 0) + ($month['utility'] ?? 0)),
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
            'wallet_amount' => $walletBalance,
            'wallet_balance' => $walletBalance,
            'valid_until' => $validUntil,
            'remaining_days' => $remainingDays,
            'service_free' => [
                'used' => $serviceUsed,
                'remaining' => max(0, $serviceLimit - $serviceUsed),
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

    private function resolveWalletBalance(): float
    {
        try {
            $balance = $this->wallet->balance();
            if ($balance > 0) {
                return round($balance, 2);
            }
        } catch (\Throwable) {
            //
        }

        // Direct read fallback (some tenants only have the row, service path edge-cases).
        $raw = WalletAccount::query()->value('balance');

        return round((float) ($raw ?? 0), 2);
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
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

            return [$name, (string) ($user->uuid ?? $user->id)];
        }

        return ['', ''];
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveValidity(mixed $expiresAt): array
    {
        if (! $expiresAt instanceof Carbon) {
            return [null, null];
        }

        $days = (int) floor((float) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false));

        return [$days, $expiresAt->toDateString()];
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
