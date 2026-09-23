<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services;

use App\Domains\Admin\Services\CrossTenantScanner;
use App\Domains\Admin\Services\WhatsappHealthAdminService;
use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\Template;
use App\Models\WaHealthAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the legacy WhatsApp Health digest payload shape used by emails.alerts.wa-health-digest.
 */
class WhatsAppHealthDigestService
{
    public function __construct(
        private readonly WhatsappHealthAdminService $fleet,
        private readonly AlertDispatcher $alerts,
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * Legacy-compatible digest summary (nested overview / messaging / usage tables).
     *
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
        $templateErrors = $this->templateErrorsForDigest(15);
        $usageToday = $this->usageRankingToday($lineRows);
        $messaging = $this->messagingOverall($lineRows);
        $customerActivity = $this->customerActivity(10);
        $operationalMeta = $this->operationalMeta($lineRows);

        $linesTotal = (int) ($kpi['lines'] ?? 0);
        $linesGreen = (int) ($kpi['green'] ?? 0);
        $linesYellow = (int) ($kpi['yellow'] ?? 0);
        $linesRed = (int) ($kpi['red'] ?? 0);
        $connected = $lineRows->filter(function (array $row): bool {
            $q = strtoupper((string) ($row['quality_rating'] ?? ''));

            return $q !== '' && $q !== 'UNKNOWN';
        })->count();

        $needsAttention = $unread > 0
            || $linesRed > 0
            || $linesYellow > 0
            || ($templateStats['rejected'] ?? 0) > 0
            || $templateErrors->isNotEmpty();

        $from = now()->subDays(6)->startOfDay();
        $to = now()->endOfDay();

        return [
            'overview' => [
                'lines' => [
                    'total' => $linesTotal,
                    'connected' => $connected,
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
            'templateErrors' => $templateErrors,
            'operationalMeta' => $operationalMeta,
            'perfFilters' => [
                'date_from' => $from->format('d M Y'),
                'date_to' => $to->format('d M Y'),
            ],
            'needsAttention' => $needsAttention,
            'leastFiveToday' => $usageToday['least'],
            'mostFiveToday' => $usageToday['most'],
            'generatedAt' => now(),
        ];
    }

    public function sendDailyDigest(bool $force = false): int
    {
        $dayKey = 'wa_health_digest_sent:'.now()->toDateString();
        if (! $force && ! Cache::add($dayKey, 1, now()->endOfDay())) {
            return 0;
        }

        $this->alerts->whatsappHealthDigest($this->buildSummary());

        return 1;
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
                ->get(['name', 'status', 'updated_at', 'payload'])
                ->map(function (Template $tpl) use ($tenant): array {
                    $payload = is_array($tpl->payload) ? $tpl->payload : [];
                    $reason = (string) ($payload['rejection_reason']
                        ?? $payload['last_status']
                        ?? $payload['error']
                        ?? '');

                    return [
                        'customer_display_name' => (string) ($tenant->company_name ?: $tenant->name ?: $tenant->id),
                        'customer_uid' => (string) $tenant->id,
                        'template_name' => (string) $tpl->name,
                        'status' => $tpl->status instanceof TemplateStatus
                            ? $tpl->status->value
                            : (string) $tpl->status,
                        'header_media_status' => null,
                        'error_reason' => $reason !== '' ? $reason : null,
                        'updated_at' => $tpl->updated_at?->format('d M Y H:i') ?? '—',
                    ];
                })
                ->all();
        });

        return $rows
            ->map(fn (array $row): object => (object) $row)
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineRows
     * @return array{least: list<array{name: string, uid: string, outbound: int}>, most: list<array{name: string, uid: string, outbound: int}>}
     */
    private function usageRankingToday(Collection $lineRows): array
    {
        $byTenant = $this->scanner->map(function ($tenant): array {
            $start = now()->startOfDay();
            $outbound = Message::query()
                ->where('direction', 'outbound')
                ->where('created_at', '>=', $start)
                ->count();

            $name = (string) ($tenant->company_name ?: $tenant->name ?: $tenant->id);

            return [[
                'name' => $name,
                'uid' => (string) $tenant->id,
                'outbound' => $outbound,
            ]];
        });

        if ($byTenant->isEmpty()) {
            // Fallback: approximate from fleet line counters when today scan is empty.
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
        $least = $sorted->take(5)->values()->all();
        $most = $sorted->sortByDesc('outbound')->take(5)->values()->all();

        return ['least' => $least, 'most' => $most];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineRows
     * @return array<string, float|int>
     */
    private function messagingOverall(Collection $lineRows): array
    {
        $delivered = (int) $lineRows->sum('delivered');
        $read = (int) $lineRows->sum('read');
        $failed = (int) $lineRows->sum('failed');
        $outbound = $delivered + $read + $failed;
        $sent = $outbound;
        $deliveredOk = $delivered + $read;

        $replied = (int) $this->scanner->map(function (): array {
            return [[
                'n' => Message::query()
                    ->where('direction', 'inbound')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ]];
        })->sum('n');

        return [
            'outbound' => $outbound,
            'sent' => $sent,
            'delivered' => $deliveredOk,
            'replied' => $replied,
            'subscribers_unsub' => 0,
            'subscribers_total' => 0,
            'send_rate' => $outbound > 0 ? round(($sent / $outbound) * 100, 1) : 0.0,
            'delivery_rate' => $sent > 0 ? round(($deliveredOk / $sent) * 100, 1) : 0.0,
            'response_rate' => $deliveredOk > 0 ? round(($replied / $deliveredOk) * 100, 1) : 0.0,
            'unsubscribe_rate' => 0.0,
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

            $display = (string) ($tenant->company_name ?: $tenant->name ?: $tenant->id);

            return [[
                'customer_display_name' => $display,
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
     * @param  Collection<int, array<string, mixed>>  $lineRows
     * @return array<string, mixed>
     */
    private function operationalMeta(Collection $lineRows): array
    {
        $withoutQuality = $lineRows->filter(function (array $row): bool {
            $q = strtoupper(trim((string) ($row['quality_rating'] ?? '')));

            return $q === '' || $q === 'UNKNOWN' || $q === '—';
        })->count();

        $lastSnapshotAt = null;
        $central = (string) config('tenancy.database.central_connection', config('database.default'));
        if (Schema::connection($central)->hasTable('wa_health_alerts')) {
            $lastSnapshotAt = WaHealthAlert::query()->max('occurred_at');
        }

        return [
            'last_snapshot' => $lastSnapshotAt
                ? [
                    'at' => Carbon::parse($lastSnapshotAt)->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST',
                    'lines' => $lineRows->count(),
                ]
                : null,
            'lines_without_quality' => $withoutQuality,
        ];
    }
}
