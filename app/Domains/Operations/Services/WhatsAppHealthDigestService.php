<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services;

use App\Domains\Admin\Services\CrossTenantScanner;
use App\Domains\Admin\Services\WhatsappHealthAdminService;
use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Enums\ContactOptInStatus;
use App\Enums\RecordStatus;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Template;
use App\Models\WaHealthAlert;
use App\Models\WhatsappLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the WhatsApp Health digest payload used by emails.alerts.wa-health-digest.
 */
class WhatsAppHealthDigestService
{
    public function __construct(
        private readonly WhatsappHealthAdminService $fleet,
        private readonly AlertDispatcher $alerts,
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(): array
    {
        $fleet = $this->fleet->fleet([], 1, 5000);
        $kpi = $fleet['kpi'];
        /** @var Collection<int, array<string, mixed>> $lineRows */
        $lineRows = collect($fleet['items']->items());

        $unread = 0;
        $critical7d = 0;
        $central = (string) config('tenancy.database.central_connection', config('database.default'));

        if (Schema::connection($central)->hasTable('wa_health_alerts')) {
            $unread = WaHealthAlert::query()->where('is_read', false)->count();
            $critical7d = WaHealthAlert::query()
                ->where('severity', 'critical')
                ->where('occurred_at', '>=', now()->subDays(7))
                ->count();
        }

        $templateStats = $this->templateStats();
        $templateErrors = $this->templateErrorsForDigest(40);
        $usageToday = $this->usageRankingToday($lineRows);
        $messaging = $this->messagingOverall($lineRows);
        $customerActivity = $this->customerActivity(10);
        $activityBuckets = $this->customerActivityBuckets(15);
        $operationalMeta = $this->operationalMeta($lineRows);
        $connection = $this->lineConnectionStats();
        $rejectedByCustomer = $this->rejectedTemplatesByCustomer($templateErrors, 8);

        $linesTotal = (int) ($kpi['lines'] ?? $connection['total']);
        $linesGreen = (int) ($kpi['green'] ?? 0);
        $linesYellow = (int) ($kpi['yellow'] ?? 0);
        $linesRed = (int) ($kpi['red'] ?? 0);
        $connected = (int) ($connection['connected'] ?? 0);
        $disconnected = (int) ($connection['disconnected'] ?? 0);
        $unrated = (int) ($operationalMeta['lines_without_quality'] ?? 0);

        $failed = max(0, (int) ($messaging['sent'] ?? 0) - (int) ($messaging['delivered'] ?? 0));
        if ($failed === 0) {
            $failed = (int) ($kpi['failed_messages'] ?? 0);
        }
        $failedPct = (float) ($messaging['sent'] ?? 0) > 0
            ? round(($failed / (float) $messaging['sent']) * 100, 1)
            : 0.0;

        $rejected = (int) ($templateStats['rejected'] ?? 0);
        $pending = (int) ($templateStats['pending'] ?? 0);
        $rejectedThisWeek = $this->rejectedTemplatesThisWeekCount();

        $from = now()->subDays(6)->startOfDay();
        $to = now()->endOfDay();
        $generatedAt = now()->timezone('Asia/Kolkata');

        $actionItems = $this->buildActionItems(
            failed: $failed,
            failedPct: $failedPct,
            disconnected: $disconnected,
            linesTotal: $linesTotal,
            qualityNeedWork: $linesRed + $linesYellow,
            qualityNames: $this->qualityProblemNames($lineRows, 12),
            rejected: $rejected,
            rejectedThisWeek: $rejectedThisWeek,
            pending: $pending,
        );

        $needsAttention = $actionItems !== [];

        $email = $this->buildEmailPresentation(
            generatedAt: $generatedAt,
            actionItems: $actionItems,
            failed: $failed,
            failedPct: $failedPct,
            disconnected: $disconnected,
            rejected: $rejected,
            messaging: $messaging,
            linesTotal: $linesTotal,
            connected: $connected,
            linesGreen: $linesGreen,
            linesYellow: $linesYellow,
            linesRed: $linesRed,
            unrated: $unrated,
            operationalMeta: $operationalMeta,
            rejectedByCustomer: $rejectedByCustomer,
            activityBuckets: $activityBuckets,
            dateFrom: $from,
            dateTo: $to,
        );

        return [
            'overview' => [
                'lines' => [
                    'total' => $linesTotal,
                    'connected' => $connected,
                    'disconnected' => $disconnected,
                    'green' => $linesGreen,
                    'yellow' => $linesYellow,
                    'red' => $linesRed,
                ],
                'templates' => $templateStats,
                'alerts' => [
                    'critical_7d' => $critical7d,
                ],
            ],
            'unread' => $unread,
            'messaging' => [
                'overall' => $messaging,
            ],
            'customerActivity' => $customerActivity,
            'activityBuckets' => $activityBuckets,
            'templateErrors' => $templateErrors,
            'rejectedByCustomer' => $rejectedByCustomer,
            'actionItems' => $actionItems,
            'operationalMeta' => $operationalMeta,
            'perfFilters' => [
                'date_from' => $from->timezone('Asia/Kolkata')->format('d M Y'),
                'date_to' => $to->timezone('Asia/Kolkata')->format('d M Y'),
            ],
            'needsAttention' => $needsAttention,
            'leastFiveToday' => $usageToday['least'],
            'mostFiveToday' => $usageToday['most'],
            'generatedAt' => $generatedAt,
            'email' => $email,
        ];
    }

    /**
     * Render digest HTML without sending (for dry-run / preview).
     *
     * @param  array<string, mixed>|null  $summary
     */
    public function renderHtml(?array $summary = null): string
    {
        $summary ??= $this->buildSummary();

        return view('emails.alerts.wa-health-digest', [
            'summary' => $summary,
            'admin' => null,
            'healthUrl' => route('admin.whatsapp-health.index'),
            'messagePerformanceUrl' => route('admin.message-performance.index'),
        ])->render();
    }

    public function sendDailyDigest(bool $force = false, ?array $overrideEmails = null): int
    {
        $dayKey = 'wa_health_digest_sent:'.now()->toDateString();
        if (! $force && ! Cache::add($dayKey, 1, now()->endOfDay())) {
            return 0;
        }

        $this->alerts->whatsappHealthDigest($this->buildSummary(), $overrideEmails);

        return 1;
    }

    /**
     * @param  list<array{severity: string, title: string, body: string, href?: string|null, cta?: string|null}>  $actionItems
     * @param  array<string, float|int>  $messaging
     * @param  array<string, mixed>  $operationalMeta
     * @param  list<array<string, mixed>>  $rejectedByCustomer
     * @param  array{active: array{count: int, names: list<string>}, quiet: array{count: int, names: list<string>}, inactive: array{count: int, names: list<string>}}  $activityBuckets
     * @return array<string, mixed>
     */
    private function buildEmailPresentation(
        Carbon $generatedAt,
        array $actionItems,
        int $failed,
        float $failedPct,
        int $disconnected,
        int $rejected,
        array $messaging,
        int $linesTotal,
        int $connected,
        int $linesGreen,
        int $linesYellow,
        int $linesRed,
        int $unrated,
        array $operationalMeta,
        array $rejectedByCustomer,
        array $activityBuckets,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): array {
        $dateShort = $generatedAt->format('j M Y');
        $dateCompact = $generatedAt->format('j M');
        $hour = (int) $generatedAt->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        $actionCount = count($actionItems);
        $headline = $actionCount > 0
            ? ($actionCount === 1
                ? '1 item needs action today.'
                : "{$actionCount} items need action today.")
            : 'No critical action items today.';

        // Prefer the undelivered copy from the design: "...messages were not delivered this week."
        $headlineFailedCount = $failed > 0 ? $failed : null;
        $headlineDetail = $headlineFailedCount !== null
            ? null
            : (($actionItems[0]['title'] ?? null) !== null
                ? 'The biggest one: '.(string) $actionItems[0]['title'].'.'
                : 'Delivery, connectivity, and templates look stable.');

        $failedCompact = $this->compactNumber($failed);
        $subjectParts = array_values(array_filter([
            $failed > 0 ? $failedCompact.' undelivered' : null,
            $disconnected > 0 ? $disconnected.' disconnected' : null,
            $rejected > 0 ? $rejected.' rejected templates' : null,
        ]));
        $subjectTail = $subjectParts !== [] ? implode(', ', $subjectParts) : 'all clear';
        $subject = "Tittu Health {$dateCompact}: {$subjectTail}";

        $deliveryRate = (float) ($messaging['delivery_rate'] ?? 0);
        $snapshotAt = $operationalMeta['last_snapshot']['at'] ?? null;
        $snapshotAgeDays = $operationalMeta['snapshot_age_days'] ?? null;
        $freshness = null;
        if (is_int($snapshotAgeDays) && $snapshotAgeDays >= 1) {
            $freshness = [
                'title' => $snapshotAgeDays === 1
                    ? 'Quality data is 1 day old.'
                    : "Quality data is {$snapshotAgeDays} days old.",
                'body' => 'Last refreshed '.($snapshotAt ?: 'unknown')
                    .($unrated > 0 ? ", so number ratings below may be out of date. {$unrated} numbers have no rating at all." : '.'),
            ];
        }

        $preheaderBits = array_values(array_filter([
            $actionCount > 0 ? "{$actionCount} items need action today" : 'Health looks stable',
            'Delivery rate '.number_format($deliveryRate, 1).'%',
            is_int($snapshotAgeDays) && $snapshotAgeDays >= 1
                ? "quality data is {$snapshotAgeDays} day".($snapshotAgeDays === 1 ? '' : 's').' old'
                : null,
        ]));

        return [
            'subject' => $subject,
            'preheader' => implode('. ', $preheaderBits).'.',
            'greeting' => $greeting,
            'headline' => $headline,
            'headline_detail' => $headlineDetail,
            'headline_failed_count' => $headlineFailedCount,
            'date_label' => $generatedAt->format('D, j M Y'),
            'date_short' => $dateShort,
            'period_label' => $dateFrom->timezone('Asia/Kolkata')->format('j M')
                .' to '.$dateTo->timezone('Asia/Kolkata')->format('j M Y'),
            'freshness' => $freshness,
            'action_items' => $actionItems,
            'scorecard' => [
                'delivery_rate' => $deliveryRate,
                'delivery_rate_color' => $deliveryRate < 90 ? '#d64545' : '#1f9d55',
                'delivered' => (int) ($messaging['delivered'] ?? 0),
                'sent' => (int) ($messaging['sent'] ?? 0),
                'response_rate' => (float) ($messaging['response_rate'] ?? 0),
                'response_rate_color' => ((float) ($messaging['response_rate'] ?? 0)) >= 30 ? '#1f9d55' : '#d64545',
                'replied' => (int) ($messaging['replied'] ?? 0),
                'connected' => $connected,
                'lines_total' => $linesTotal,
                'green' => $linesGreen,
                'yellow' => $linesYellow,
                'red' => $linesRed,
                'unrated' => $unrated,
                'unsubscribes_tracked' => ((int) ($messaging['subscribers_total'] ?? 0)) > 0,
                'unsubscribe_rate' => (float) ($messaging['unsubscribe_rate'] ?? 0),
                'unsubscribed' => (int) ($messaging['unsubscribed_period'] ?? 0),
                'subscribers_total' => (int) ($messaging['subscribers_total'] ?? 0),
                'unsubscribed_lifetime' => (int) ($messaging['subscribers_unsub'] ?? 0),
            ],
            'rejected_by_customer' => $rejectedByCustomer,
            'rejected_total' => $rejected,
            'activity' => $activityBuckets,
            'activity_more_url' => route('admin.message-performance.index', [
                'sort' => 'sent',
                'direction' => 'asc',
            ]),
            'footer_stamp' => $generatedAt->format('j M Y, H:i').' IST',
            'snapshot_label' => $snapshotAt,
        ];
    }

    /**
     * @param  list<string>  $qualityNames
     * @return list<array{severity: string, title: string, body: string, businesses?: list<string>, href: string|null, cta: string|null}>
     */
    private function buildActionItems(
        int $failed,
        float $failedPct,
        int $disconnected,
        int $linesTotal,
        int $qualityNeedWork,
        array $qualityNames,
        int $rejected,
        int $rejectedThisWeek,
        int $pending,
    ): array {
        $healthUrl = route('admin.whatsapp-health.index');
        $items = [];

        if ($failed > 0) {
            $items[] = [
                'severity' => 'critical',
                'title' => number_format($failed).' messages not delivered ('.$failedPct.'%)',
                'body' => 'Last 7 days. See which customers and failure reasons are driving this.',
                'href' => $healthUrl,
                'cta' => 'View delivery failures',
            ];
        }

        if ($disconnected > 0) {
            $items[] = [
                'severity' => 'critical',
                'title' => $disconnected.' of '.$linesTotal.' numbers disconnected',
                'body' => 'These customers cannot send messages until reconnected.',
                'href' => $healthUrl,
                'cta' => 'View disconnected numbers',
            ];
        }

        if ($qualityNeedWork > 0) {
            $items[] = [
                'severity' => 'warning',
                'title' => $qualityNeedWork.' number'.($qualityNeedWork === 1 ? '' : 's').' need quality work',
                'body' => 'Low quality ratings risk Meta limiting their messaging.',
                'businesses' => array_values($qualityNames),
                'href' => $healthUrl.(str_contains($healthUrl, '?') ? '&' : '?').'quality=RED',
                'cta' => 'View numbers',
            ];
        }

        if ($rejected > 0 || $pending > 0) {
            $bodyBits = array_values(array_filter([
                $rejectedThisWeek > 0 ? $rejectedThisWeek.' new this week' : null,
                $pending > 0 ? $pending.' pending review' : null,
            ]));
            $items[] = [
                'severity' => 'warning',
                'title' => number_format($rejected).' rejected or disabled templates',
                'body' => $bodyBits !== [] ? implode(', ', $bodyBits).'. Breakdown below.' : 'Breakdown below.',
                'href' => $healthUrl.(str_contains($healthUrl, '?') ? '&' : '?').'tab=templates&template_status=REJECTED',
                'cta' => null,
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineRows
     * @return list<string>
     */
    private function qualityProblemNames(Collection $lineRows, int $limit): array
    {
        return $lineRows
            ->filter(function (array $row): bool {
                $q = strtoupper((string) ($row['quality_rating'] ?? ''));

                return in_array($q, ['RED', 'YELLOW'], true);
            })
            ->map(fn (array $row): string => trim((string) ($row['tenant_name'] ?? $row['display_name'] ?? $row['phone'] ?? '')))
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array{total: int, connected: int, disconnected: int}
     */
    private function lineConnectionStats(): array
    {
        $rows = $this->scanner->map(function (): array {
            return WhatsappLine::query()
                ->get(['id', 'waba_id', 'alibaba_cust_space_id', 'status'])
                ->map(function (WhatsappLine $line): array {
                    $active = $line->status === null || $line->status === RecordStatus::Active;
                    $connected = $active && $line->isConnected();

                    return ['connected' => $connected];
                })
                ->all();
        });

        $total = $rows->count();
        $connected = $rows->filter(fn (array $r): bool => (bool) ($r['connected'] ?? false))->count();

        return [
            'total' => $total,
            'connected' => $connected,
            'disconnected' => max(0, $total - $connected),
        ];
    }

    /**
     * @return array{total: int, pending: int, rejected: int}
     */
    private function templateStats(): array
    {
        $rows = $this->scanner->map(function (): array {
            return [[
                'total' => Template::query()->count(),
                'pending' => Template::query()->where('status', TemplateStatus::PendingReview)->count(),
                'rejected' => Template::query()->where('status', TemplateStatus::Rejected)->count(),
            ]];
        });

        return [
            'total' => (int) $rows->sum('total'),
            'pending' => (int) $rows->sum('pending'),
            'rejected' => (int) $rows->sum('rejected'),
        ];
    }

    private function rejectedTemplatesThisWeekCount(): int
    {
        $since = now()->subDays(7);

        return (int) $this->scanner->map(function () use ($since): array {
            return [[
                'n' => Template::query()
                    ->where('status', TemplateStatus::Rejected)
                    ->where('updated_at', '>=', $since)
                    ->count(),
            ]];
        })->sum('n');
    }

    /**
     * @return Collection<int, object>
     */
    private function templateErrorsForDigest(int $limit): Collection
    {
        $rows = $this->scanner->map(function ($tenant) use ($limit): array {
            return Template::query()
                ->where('status', TemplateStatus::Rejected)
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get(['name', 'status', 'updated_at', 'payload', 'rejection_reason'])
                ->map(function (Template $tpl) use ($tenant): array {
                    $payload = is_array($tpl->payload) ? $tpl->payload : [];
                    $reason = trim((string) (
                        $tpl->rejection_reason
                        ?? $payload['rejection_reason']
                        ?? $payload['last_status']
                        ?? $payload['error']
                        ?? ''
                    ));

                    return [
                        'customer_display_name' => (string) ($tenant->company_name ?: $tenant->name ?: $tenant->id),
                        'customer_uid' => (string) $tenant->id,
                        'template_name' => (string) $tpl->name,
                        'status' => $tpl->status instanceof TemplateStatus
                            ? $tpl->status->value
                            : (string) $tpl->status,
                        'header_media_status' => null,
                        'error_reason' => $reason !== '' ? $reason : null,
                        'updated_at' => $tpl->updated_at?->timezone('Asia/Kolkata')->format('j M') ?? '-',
                        'updated_at_raw' => $tpl->updated_at,
                    ];
                })
                ->all();
        });

        return $rows
            ->map(fn (array $row): object => (object) $row)
            ->sortByDesc(fn (object $row) => $row->updated_at_raw instanceof Carbon ? $row->updated_at_raw->timestamp : 0)
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, object>  $templateErrors
     * @return list<array{customer: string, rejected: int, templates: string, reason: string, dates: string}>
     */
    private function rejectedTemplatesByCustomer(Collection $templateErrors, int $limit): array
    {
        return $templateErrors
            ->groupBy(fn (object $row): string => (string) ($row->customer_display_name ?? $row->customer_uid ?? 'Unknown'))
            ->map(function (Collection $group, string $customer): array {
                $names = $group->pluck('template_name')->filter()->values();
                $shown = $names->take(3)->all();
                $extra = max(0, $names->count() - 3);
                $templateLabel = implode(', ', $shown).($extra > 0 ? ' +'.$extra.' more' : '');
                $dates = $group->pluck('updated_at')->filter()->unique()->take(3)->implode(', ');
                $reason = $group
                    ->pluck('error_reason')
                    ->filter()
                    ->unique()
                    ->take(2)
                    ->implode('; ');

                return [
                    'customer' => $customer,
                    'rejected' => $group->count(),
                    'templates' => $templateLabel !== '' ? $templateLabel : '—',
                    'reason' => $reason !== '' ? $reason : 'Not captured',
                    'dates' => $dates !== '' ? $dates : '—',
                ];
            })
            ->sortByDesc('rejected')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineRows
     * @return array{least: list<array{name: string, uid: string, outbound: int}>, most: list<array{name: string, uid: string, outbound: int}>}
     */
    private function usageRankingToday(Collection $lineRows): array
    {
        $excluded = array_map('strtolower', (array) config('services.wa_health.performance_excluded_emails', []));

        $byTenant = $this->scanner->map(function ($tenant) use ($excluded): array {
            $email = strtolower((string) ($tenant->email ?? ''));
            if ($email !== '' && in_array($email, $excluded, true)) {
                return [];
            }

            $outbound = Message::query()
                ->where('direction', 'outbound')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();

            return [[
                'name' => (string) ($tenant->company_name ?: $tenant->name ?: $tenant->id),
                'uid' => (string) $tenant->id,
                'outbound' => $outbound,
            ]];
        });

        if ($byTenant->isEmpty()) {
            $byTenant = $lineRows
                ->groupBy(fn (array $r) => (string) ($r['tenant_id'] ?? 'unknown'))
                ->map(function (Collection $group, string $tenantId): array {
                    $outbound = (int) $group->sum(fn (array $r) => (int) ($r['delivered'] ?? 0)
                        + (int) ($r['read'] ?? 0)
                        + (int) ($r['failed'] ?? 0));

                    return [
                        'name' => (string) ($group->first()['tenant_name'] ?? $tenantId),
                        'uid' => $tenantId,
                        'outbound' => $outbound,
                    ];
                })
                ->values();
        }

        $sorted = $byTenant->sortBy('outbound')->values();

        return [
            'least' => $sorted->take(5)->values()->all(),
            'most' => $sorted->sortByDesc('outbound')->take(5)->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineRows
     * @return array<string, float|int>
     */
    private function messagingOverall(Collection $lineRows): array
    {
        $since = now()->subDays(7);

        $totals = $this->scanner->map(function () use ($since): array {
            $base = Message::query()
                ->where('direction', 'outbound')
                ->where('created_at', '>=', $since);

            $sent = (clone $base)->count();
            $deliveredOk = (clone $base)
                ->whereIn('status', [
                    \App\Enums\MessageStatus::Delivered->value,
                    \App\Enums\MessageStatus::Read->value,
                ])
                ->count();
            $failed = (clone $base)
                ->where('status', \App\Enums\MessageStatus::Failed->value)
                ->count();
            $replied = Message::query()
                ->where('direction', 'inbound')
                ->where('created_at', '>=', $since)
                ->count();

            $subscribersTotal = Contact::query()->count();
            $subscribersUnsub = Contact::query()
                ->where(function ($q): void {
                    $q->where('status', ContactStatus::Unsubscribed)
                        ->orWhere('opt_in_status', ContactOptInStatus::OptedOut);
                })
                ->count();
            // New opt-outs in the digest window (STOP / unsubscribe).
            $unsubscribedPeriod = Contact::query()
                ->whereNotNull('opted_out_at')
                ->where('opted_out_at', '>=', $since)
                ->count();

            return [[
                'sent' => $sent,
                'delivered' => $deliveredOk,
                'failed' => $failed,
                'replied' => $replied,
                'subscribers_total' => $subscribersTotal,
                'subscribers_unsub' => $subscribersUnsub,
                'unsubscribed_period' => $unsubscribedPeriod,
            ]];
        });

        $sent = (int) $totals->sum('sent');
        $deliveredOk = (int) $totals->sum('delivered');
        $failed = (int) $totals->sum('failed');
        $replied = (int) $totals->sum('replied');
        $subscribersTotal = (int) $totals->sum('subscribers_total');
        $subscribersUnsub = (int) $totals->sum('subscribers_unsub');
        $unsubscribedPeriod = (int) $totals->sum('unsubscribed_period');

        // Fallback to fleet lifetime counters if the 7-day scan is empty.
        if ($sent === 0 && $lineRows->isNotEmpty()) {
            $delivered = (int) $lineRows->sum('delivered');
            $read = (int) $lineRows->sum('read');
            $failed = (int) $lineRows->sum('failed');
            $sent = $delivered + $read + $failed;
            $deliveredOk = $delivered + $read;
        }

        $unsubscribeRate = $deliveredOk > 0
            ? round(($unsubscribedPeriod / $deliveredOk) * 100, 1)
            : ($subscribersTotal > 0
                ? round(($unsubscribedPeriod / $subscribersTotal) * 100, 1)
                : 0.0);

        return [
            'outbound' => $sent,
            'sent' => $sent,
            'delivered' => $deliveredOk,
            'failed' => $failed,
            'replied' => $replied,
            'subscribers_unsub' => $subscribersUnsub,
            'subscribers_total' => $subscribersTotal,
            'unsubscribed_period' => $unsubscribedPeriod,
            'send_rate' => $sent > 0 ? 100.0 : 0.0,
            'delivery_rate' => $sent > 0 ? round(($deliveredOk / $sent) * 100, 1) : 0.0,
            'response_rate' => $deliveredOk > 0 ? round(($replied / $deliveredOk) * 100, 1) : 0.0,
            'unsubscribe_rate' => $unsubscribeRate,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function customerActivity(int $limit): Collection
    {
        $rows = $this->scanner->map(function ($tenant): array {
            $since = now()->subDays(7);
            $lastCampaign = Campaign::query()
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $since)
                ->orderByDesc('completed_at')
                ->first(['name', 'completed_at']);

            $lastMessage = Message::query()
                ->where('direction', 'outbound')
                ->where('created_at', '>=', $since)
                ->orderByDesc('created_at')
                ->first(['created_at']);

            if ($lastCampaign === null && $lastMessage === null) {
                return [];
            }

            return [[
                'customer_display_name' => (string) ($tenant->company_name ?: $tenant->name ?: $tenant->id),
                'customer_uid' => (string) $tenant->id,
                'business_name' => (string) ($tenant->company_name ?? ''),
                'last_campaign_at' => $lastCampaign?->completed_at,
                'last_campaign_name' => $lastCampaign?->name,
                'last_message_at' => $lastMessage?->created_at,
            ]];
        });

        return $rows
            ->sortByDesc(function (array $row) {
                $a = $row['last_campaign_at'] ?? null;
                $b = $row['last_message_at'] ?? null;
                $ta = $a instanceof Carbon ? $a->timestamp : 0;
                $tb = $b instanceof Carbon ? $b->timestamp : 0;

                return max($ta, $tb);
            })
            ->take($limit)
            ->map(function (array $row): object {
                $campaignAt = $row['last_campaign_at'] ?? null;
                $messageAt = $row['last_message_at'] ?? null;

                return (object) [
                    'customer_display_name' => $row['customer_display_name'],
                    'customer_uid' => $row['customer_uid'],
                    'business_name' => $row['business_name'] !== '' ? $row['business_name'] : null,
                    'last_campaign_at' => $campaignAt instanceof Carbon
                        ? $campaignAt->timezone('Asia/Kolkata')->format('d M Y, h:i A')
                        : null,
                    'last_campaign_name' => $row['last_campaign_name'] ?? null,
                    'last_message_at' => $messageAt instanceof Carbon
                        ? $messageAt->timezone('Asia/Kolkata')->format('d M Y, h:i A')
                        : null,
                ];
            })
            ->values();
    }

    /**
     * @return array{
     *     active: array{count: int, names: list<string>},
     *     quiet: array{count: int, names: list<string>},
     *     inactive: array{count: int, names: list<string>}
     * }
     */
    private function customerActivityBuckets(int $nameLimit): array
    {
        $excluded = array_map('strtolower', (array) config('services.wa_health.performance_excluded_emails', []));

        $rows = $this->scanner->map(function ($tenant) use ($excluded): array {
            $email = strtolower((string) ($tenant->email ?? ''));
            if ($email !== '' && in_array($email, $excluded, true)) {
                return [];
            }

            $lastMessage = Message::query()
                ->where('direction', 'outbound')
                ->orderByDesc('created_at')
                ->value('created_at');
            $lastCampaign = Campaign::query()
                ->whereNotNull('completed_at')
                ->orderByDesc('completed_at')
                ->value('completed_at');

            $timestamps = array_values(array_filter([
                $lastMessage ? Carbon::parse($lastMessage)->timestamp : null,
                $lastCampaign ? Carbon::parse($lastCampaign)->timestamp : null,
            ]));
            $lastAt = $timestamps !== [] ? Carbon::createFromTimestamp(max($timestamps)) : null;
            $name = (string) ($tenant->company_name ?: $tenant->name ?: $tenant->id);

            return [[
                'name' => $name,
                'last_at' => $lastAt,
                'label' => $lastAt?->timezone('Asia/Kolkata')->format('j M'),
            ]];
        });

        $active = [];
        $quiet = [];
        $inactive = [];
        $now = now();

        foreach ($rows as $row) {
            /** @var Carbon|null $lastAt */
            $lastAt = $row['last_at'] ?? null;
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if ($lastAt instanceof Carbon && $lastAt->gte($now->copy()->subDay())) {
                $active[] = $name;
            } elseif ($lastAt instanceof Carbon && $lastAt->gte($now->copy()->subDays(7))) {
                $label = (string) ($row['label'] ?? '');
                $quiet[] = $label !== '' ? "{$name} ({$label})" : $name;
            } else {
                $inactive[] = $name;
            }
        }

        return [
            'active' => [
                'count' => count($active),
                'names' => array_slice($active, 0, $nameLimit),
            ],
            'quiet' => [
                'count' => count($quiet),
                'names' => array_slice($quiet, 0, $nameLimit),
            ],
            'inactive' => [
                'count' => count($inactive),
                'names' => array_slice($inactive, 0, $nameLimit),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineRows
     * @return array<string, mixed>
     */
    private function operationalMeta(Collection $lineRows): array
    {
        $withoutQuality = $lineRows->filter(function (array $row): bool {
            $q = strtoupper(trim((string) ($row['quality_rating'] ?? '')));

            return $q === '' || $q === 'UNKNOWN' || $q === '-';
        })->count();

        $lastSnapshotAt = null;
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        if (Schema::connection($central)->hasTable('wa_health_alerts')) {
            $lastSnapshotAt = WaHealthAlert::query()->max('occurred_at');
        }

        $snapshotAgeDays = null;
        $snapshotLabel = null;
        if ($lastSnapshotAt) {
            $parsed = Carbon::parse($lastSnapshotAt)->timezone('Asia/Kolkata');
            $snapshotLabel = $parsed->format('j M Y, H:i').' IST';
            $snapshotAgeDays = (int) $parsed->diffInDays(now()->timezone('Asia/Kolkata'));
        }

        return [
            'last_snapshot' => $lastSnapshotAt
                ? [
                    'at' => $snapshotLabel,
                    'lines' => $lineRows->count(),
                ]
                : null,
            'snapshot_age_days' => $snapshotAgeDays,
            'lines_without_quality' => $withoutQuality,
        ];
    }

    private function compactNumber(int $n): string
    {
        if ($n >= 1000000) {
            return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.').'M';
        }
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.').'k';
        }

        return (string) $n;
    }
}
