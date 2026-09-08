<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Billing\Services\WalletService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\ContactOptInStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WhatsappLine;
use App\Support\PublicId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_YEARLY = 'yearly';

    public const PERIOD_ALL = 'all';

    /** @var list<string> */
    public const PERIODS = [
        self::PERIOD_DAILY,
        self::PERIOD_WEEKLY,
        self::PERIOD_MONTHLY,
        self::PERIOD_YEARLY,
        self::PERIOD_ALL,
    ];

    public function __construct(
        private readonly WalletService $walletService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function indexPayload(User $user): array
    {
        $creditsPeriod = self::normalizePeriod(request()->string('credits_period')->toString());
        $campaign = $this->resolveCampaignFromRequest();

        return [
            'greeting' => $this->greeting($user),
            'walletBalance' => $this->walletBalance(),
            'subscription' => $this->subscriptionCard(),
            'credits' => $this->creditsSummary($creditsPeriod),
            'creditsPeriod' => $creditsPeriod,
            'growthMetrics' => $this->growthMetrics(),
            'subscriberSeries' => $this->subscriberSeries(),
            'recentCampaigns' => $this->recentCampaigns(),
            'selectedCampaign' => $this->selectedCampaign($campaign),
            'campaignRecipients' => $this->selectedCampaignRecipients($campaign),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function creditsPayload(?string $period = null): array
    {
        $period = self::normalizePeriod($period);

        return [
            'period' => $period,
            'credits' => $this->creditsSummary($period),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function campaignReviewPayload(?Campaign $campaign = null): array
    {
        $campaign = $this->selectedCampaign($campaign);
        $recipients = $this->selectedCampaignRecipients($campaign);
        $total = (int) ($campaign?->total_recipients ?? 0);
        $pct = static fn (int $val): int => $total > 0 ? (int) round(($val / $total) * 100) : 0;

        $detailsBase = $campaign
            ? route('campaigns.statistics.detail', $campaign)
            : route('campaigns.index');
        $statsUrl = $campaign
            ? route('campaigns.statistics', $campaign)
            : route('campaigns.index');

        return [
            'campaign' => $campaign === null ? null : [
                'id' => $campaign->uuid,
                'name' => $campaign->name,
                'total_recipients' => $total,
                'total_delivered' => (int) $campaign->total_delivered,
                'total_failed' => (int) $campaign->total_failed,
                'total_read' => (int) $campaign->total_read,
                'total_response' => (int) ($campaign->total_response ?? 0),
                'total_unsubscribed' => (int) ($campaign->total_unsubscribed ?? 0),
                'details_base' => $detailsBase,
                'stats_url' => $statsUrl,
                'metrics' => [
                    'total' => ['count' => $total, 'percent' => 100],
                    'delivered' => ['count' => (int) $campaign->total_delivered, 'percent' => $pct((int) $campaign->total_delivered)],
                    'failed' => ['count' => (int) $campaign->total_failed, 'percent' => $pct((int) $campaign->total_failed)],
                    'read' => ['count' => (int) $campaign->total_read, 'percent' => $pct((int) $campaign->total_read)],
                    'response' => ['count' => (int) ($campaign->total_response ?? 0), 'percent' => $pct((int) ($campaign->total_response ?? 0))],
                    'unsubscribed' => ['count' => (int) ($campaign->total_unsubscribed ?? 0), 'percent' => $pct((int) ($campaign->total_unsubscribed ?? 0))],
                ],
            ],
            'recipients' => $recipients->map(function (CampaignRecipient $recipient) use ($campaign): array {
                $status = $recipient->status;

                return [
                    'name' => $recipient->contact?->name ?: '—',
                    'phone' => $recipient->contact_phone ?: ($recipient->contact?->phone ?? '—'),
                    'campaign' => $campaign?->name ?? '—',
                    'sent_at' => optional($recipient->sent_at ?? $recipient->delivered_at ?? $recipient->created_at)->format('d M Y h:i A') ?? '—',
                    'status_label' => $status?->label() ?? 'Pending',
                    'status_variant' => match ($status?->value) {
                        'delivered' => 'done',
                        'failed' => 'rejected',
                        'read' => 'approved',
                        'response' => 'active',
                        'sent' => 'sent',
                        'unsubscribed' => 'inactive',
                        default => 'pending',
                    },
                ];
            })->values()->all(),
        ];
    }

    public function walletRechargePayload(): array
    {
        $amount = (float) config('billing.wallet.default_recharge_amount', 5000);
        $totals = $this->subscriptionService->calculateTotals($amount);

        return [
            'rechargeAmount' => $amount,
            'rechargeTotals' => $totals,
            'quickAmounts' => config('billing.wallet.quick_recharge_amounts', [10000, 15000, 20000]),
            'razorpayConfigured' => app(\App\Domains\Billing\Services\RazorpayService::class)->isConfigured(),
        ];
    }

    public static function normalizePeriod(?string $period, string $default = self::PERIOD_DAILY): string
    {
        $period = strtolower(trim((string) $period));

        return in_array($period, self::PERIODS, true) ? $period : $default;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodRange(string $period, ?int $maxDays = null): array
    {
        $end = now();
        $period = self::normalizePeriod($period);

        $start = match ($period) {
            self::PERIOD_WEEKLY => now()->subDays(6)->startOfDay(),
            self::PERIOD_MONTHLY => now()->subDays(29)->startOfDay(),
            self::PERIOD_YEARLY => now()->subYear()->startOfDay(),
            self::PERIOD_ALL => now()->subDays(max(1, $maxDays ?? (int) config('billing.wallet.history_max_days', 365)))->startOfDay(),
            default => now()->startOfDay(),
        };

        return [$start, $end];
    }

    private function walletBalance(): float
    {
        $balance = WalletAccount::query()->value('balance');

        if ($balance !== null) {
            return (float) $balance;
        }

        return $this->walletService->balance();
    }

    /** @return array<string, mixed> */
    private function subscriptionCard(): array
    {
        $summary = $this->subscriptionService->subscriptionSummary();
        $plan = $summary['plan'];
        $subscription = $summary['subscription'];
        $expiresAt = $summary['expires_at'];
        $planName = $summary['plan_name'] ?? $plan?->name;

        $daysRemaining = null;
        $validityPercent = 0;

        if ($expiresAt instanceof Carbon) {
            $daysRemaining = max(0, (int) now()->diffInDays($expiresAt, false));
            $startsAt = $subscription?->starts_at;

            if ($startsAt instanceof Carbon && $expiresAt->greaterThan($startsAt)) {
                $totalDays = max(1, (int) $startsAt->diffInDays($expiresAt));
                $validityPercent = min(100, (int) round(($daysRemaining / $totalDays) * 100));
            } elseif ($daysRemaining > 0) {
                $validityPercent = min(100, max(8, $daysRemaining));
            }
        }

        return [
            'planName' => $planName,
            'expiresAt' => $expiresAt,
            'daysRemaining' => $daysRemaining,
            'validityPercent' => $validityPercent,
            'isCancelled' => (bool) ($summary['is_cancelled'] ?? false),
            'hasPlan' => filled($planName),
            'hasSubscription' => $subscription !== null,
        ];
    }

    /** @return array<string, int|string> */
    private function creditsSummary(string $period): array
    {
        [$from, $to] = self::periodRange($period);
        $limit = $this->conversationLimitForPeriod($period);

        $row = CampaignRecipient::query()
            ->join('campaigns', 'campaigns.id', '=', 'campaign_recipients.campaign_id')
            ->leftJoin('templates', 'templates.id', '=', 'campaigns.template_id')
            ->whereBetween(
                DB::raw('COALESCE(campaign_recipients.sent_at, campaign_recipients.delivered_at, campaign_recipients.created_at)'),
                [$from, $to],
            )
            ->where('campaign_recipients.status', '!=', CampaignRecipientStatus::Pending->value)
            ->selectRaw("
                COUNT(*) as sent,
                SUM(CASE WHEN UPPER(COALESCE(templates.category, 'MARKETING')) = 'MARKETING' THEN 1 ELSE 0 END) as marketing,
                SUM(CASE WHEN UPPER(COALESCE(templates.category, '')) = 'UTILITY' THEN 1 ELSE 0 END) as utility,
                SUM(CASE WHEN UPPER(COALESCE(templates.category, '')) IN ('AUTHENTICATION', 'SERVICE', 'LIMITED_TIME_OFFER') THEN 1 ELSE 0 END) as service
            ")
            ->first();

        $sent = (int) ($row->sent ?? 0);
        $marketing = (int) ($row->marketing ?? 0);
        $utility = (int) ($row->utility ?? 0);
        $service = (int) ($row->service ?? 0);

        // Fallback when recipients are missing but campaign counters exist for the window.
        if ($sent === 0) {
            $campaignTotals = Campaign::query()
                ->whereBetween(
                    DB::raw('COALESCE(started_at, completed_at, scheduled_at, updated_at)'),
                    [$from, $to],
                )
                ->selectRaw('
                    COALESCE(SUM(total_delivered), 0) as delivered,
                    COALESCE(SUM(total_recipients), 0) as recipients
                ')
                ->first();

            $sent = max((int) ($campaignTotals->delivered ?? 0), (int) ($campaignTotals->recipients ?? 0));
            $marketing = $sent;
        }

        return [
            'period' => $period,
            'sent' => $sent,
            'sent_limit' => $limit,
            'marketing' => $marketing,
            'marketing_limit' => $limit,
            'utility' => $utility,
            'utility_limit' => $limit,
            'service' => $service,
            'service_limit' => $limit,
        ];
    }

    private function conversationLimitForPeriod(string $period): int
    {
        $tier = WhatsappLine::query()
            ->where('is_default', true)
            ->value('messaging_limit_tier')
            ?? WhatsappLine::query()->value('messaging_limit_tier');

        $daily = match (strtoupper((string) $tier)) {
            'TIER_50' => 50,
            'TIER_250' => 250,
            'TIER_1K', 'TIER_1000' => 1000,
            'TIER_10K', 'TIER_10000' => 10000,
            'TIER_100K', 'TIER_100000' => 100000,
            'UNLIMITED' => 1000000,
            default => 1000,
        };

        return match (self::normalizePeriod($period)) {
            self::PERIOD_WEEKLY => $daily * 7,
            self::PERIOD_MONTHLY => $daily * 30,
            self::PERIOD_YEARLY, self::PERIOD_ALL => $daily * 365,
            default => $daily,
        };
    }

    /** @return array<string, int|string> */
    private function growthMetrics(): array
    {
        $subscribed = Contact::query()->where('status', ContactStatus::Subscribed)->count();
        if ($subscribed === 0) {
            $subscribed = Contact::query()->where('opt_in_status', ContactOptInStatus::OptedIn)->count();
        }

        $unsubscribed = Contact::query()->where('status', ContactStatus::Unsubscribed)->count();
        if ($unsubscribed === 0) {
            $unsubscribed = Contact::query()->where('opt_in_status', ContactOptInStatus::OptedOut)->count();
        }

        $blacklisted = Contact::query()->where('status', ContactStatus::Blacklisted)->count();
        $total = max(1, Contact::query()->count());

        return [
            'subscribed' => $subscribed,
            'unsubscribed' => $unsubscribed,
            'blacklisted' => $blacklisted,
            'subscribeRate' => number_format(($subscribed / $total) * 100, 2).'%',
            'unsubscribeRate' => number_format(($unsubscribed / $total) * 100, 2).'%',
        ];
    }

    /**
     * Monthly contact creation / opt-in counts for the subscribers chart.
     *
     * @return list<array{label: string, value: int}>
     */
    private function subscriberSeries(): array
    {
        $series = $this->buildMonthlySeries(6);

        if (collect($series)->sum('value') > 0) {
            return $series;
        }

        // Migrated contacts often sit outside the last 6 months — show the latest
        // months that actually have growth data instead of an empty chart.
        $driver = Contact::query()->getConnection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m-01', COALESCE(opted_in_at, created_at))"
            : "DATE_FORMAT(COALESCE(opted_in_at, created_at), '%Y-%m-01')";

        $rows = Contact::query()
            ->selectRaw("{$monthExpression} as month_start, COUNT(*) as total")
            ->whereRaw('COALESCE(opted_in_at, created_at) IS NOT NULL')
            ->groupBy('month_start')
            ->orderByDesc('month_start')
            ->limit(6)
            ->get()
            ->sortBy('month_start')
            ->values();

        if ($rows->isEmpty()) {
            return $series;
        }

        return $rows->map(function ($row): array {
            $month = Carbon::parse((string) $row->month_start);

            return [
                'label' => $month->format('M Y'),
                'value' => (int) $row->total,
            ];
        })->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function buildMonthlySeries(int $months): array
    {
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $start = $month->copy()->startOfMonth();
            $end = $month->copy()->endOfMonth();

            $value = Contact::query()
                ->where(function ($query) use ($start, $end): void {
                    $query->whereBetween('created_at', [$start, $end])
                        ->orWhereBetween('opted_in_at', [$start, $end]);
                })
                ->count();

            $series[] = [
                'label' => $month->format('M Y'),
                'value' => $value,
            ];
        }

        return $series;
    }

    /** @return Collection<int, Campaign> */
    private function recentCampaigns(): Collection
    {
        return Campaign::query()
            ->with(['audience:id,name', 'template:id,name', 'whatsappLine:id,phone'])
            ->orderByDesc(DB::raw('COALESCE(completed_at, started_at, scheduled_at, updated_at)'))
            ->orderByDesc('id')
            ->get();
    }

    private function selectedCampaign(?Campaign $campaign = null): ?Campaign
    {
        if ($campaign instanceof Campaign) {
            return $campaign->loadMissing(['audience:id,name', 'template:id,name', 'whatsappLine:id,phone']);
        }

        $fromRequest = $this->resolveCampaignFromRequest();
        if ($fromRequest instanceof Campaign) {
            return $fromRequest;
        }

        return $this->recentCampaigns()->first();
    }

    /** @return Collection<int, CampaignRecipient> */
    private function selectedCampaignRecipients(?Campaign $campaign = null): Collection
    {
        $campaign = $this->selectedCampaign($campaign);
        if ($campaign === null) {
            return collect();
        }

        return CampaignRecipient::query()
            ->with('contact:id,name,phone')
            ->where('campaign_id', $campaign->id)
            ->orderByDesc(DB::raw('COALESCE(sent_at, delivered_at, created_at)'))
            ->limit(25)
            ->get();
    }

    private function resolveCampaignFromRequest(): ?Campaign
    {
        $uuid = request()->input('campaign_id');
        if (! is_string($uuid) && ! is_numeric($uuid)) {
            return null;
        }

        $campaign = PublicId::find(Campaign::class, (string) $uuid);
        if ($campaign === null) {
            return null;
        }

        return $campaign->loadMissing(['audience:id,name', 'template:id,name', 'whatsappLine:id,phone']);
    }

    private function greeting(User $user): string
    {
        $hour = (int) now()->format('G');
        $timeGreeting = match (true) {
            $hour < 12 => 'Good Morning',
            $hour < 17 => 'Good Afternoon',
            default => 'Good Evening',
        };

        $name = trim((string) ($user->first_name ?: $user->name ?: 'there'));

        return "{$timeGreeting}, {$name}";
    }
}
