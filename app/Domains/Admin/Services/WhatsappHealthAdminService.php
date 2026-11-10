<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\WhatsappLine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WhatsappHealthAdminService
{
    public const DAYS_PRESETS = [7, 30, 90];

    public function __construct(
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * @param  array{q?: string, tenant_id?: string, tenant?: string, quality?: string, sort?: string, direction?: string, days?: int|string}  $filters
     * @return array{kpi: array<string, int>, items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function fleet(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $sort = (string) ($filters['sort'] ?? 'failed');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (! in_array($sort, ['failed', 'delivered', 'read', 'phone', 'quality_rating', 'tenant_name'], true)) {
            $sort = 'failed';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $days = $this->normalizeDays($filters['days'] ?? null);

        $filters = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'tenant' => trim((string) ($filters['tenant'] ?? $filters['tenant_id'] ?? '')),
            'quality' => trim((string) ($filters['quality'] ?? '')),
            'sort' => $sort,
            'direction' => $direction,
            'days' => (string) $days,
        ];

        $since = now()->subDays($days)->startOfDay();

        $rows = $this->scanner->map(function () use ($since): array {
            return $this->lineRowsForCurrentTenant($since)->all();
        }, $filters['tenant'] !== '' ? $filters['tenant'] : null);

        if ($filters['q'] !== '') {
            $q = strtolower($filters['q']);
            $rows = $rows->filter(fn ($r) => str_contains(strtolower(($r['tenant_name'] ?? '').($r['phone'] ?? '').($r['display_name'] ?? '')), $q))->values();
        }
        if ($filters['quality'] !== '') {
            $rows = $rows->filter(fn ($r) => strtoupper((string) $r['quality_rating']) === strtoupper($filters['quality']))->values();
        }

        $kpi = [
            'lines' => $rows->count(),
            'green' => $rows->filter(fn ($r) => strtoupper((string) $r['quality_rating']) === 'GREEN')->count(),
            'yellow' => $rows->filter(fn ($r) => strtoupper((string) $r['quality_rating']) === 'YELLOW')->count(),
            'red' => $rows->filter(fn ($r) => strtoupper((string) $r['quality_rating']) === 'RED')->count(),
            'failed_messages' => (int) $rows->sum('failed'),
        ];

        $rows = AdminListQuery::sortRows(
            $rows,
            $filters['sort'],
            $filters['direction'],
            ['failed', 'delivered', 'read', 'phone', 'quality_rating', 'tenant_name'],
            'failed',
        );

        $page = max(1, $page);
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter($filters)],
        );

        return ['kpi' => $kpi, 'items' => $paginator, 'filters' => $filters];
    }

    /**
     * @param  array{q?: string, tenant_id?: string, tenant?: string, sort?: string, direction?: string, days?: int|string}  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>, kpi: array<string, int>}
     */
    public function messagePerformance(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $sort = (string) ($filters['sort'] ?? 'delivery_rate');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (! in_array($sort, ['delivery_rate', 'read_rate', 'failed', 'tenant_name', 'sent'], true)) {
            $sort = 'delivery_rate';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $days = $this->normalizeDays($filters['days'] ?? null);
        $since = now()->subDays($days)->startOfDay();

        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'tenant' => trim((string) ($filters['tenant'] ?? $filters['tenant_id'] ?? '')),
            'sort' => $sort,
            'direction' => $direction,
            'days' => (string) $days,
        ];

        $rows = $this->scanner->map(function () use ($since): array {
            return $this->lineRowsForCurrentTenant($since)
                ->map(function (array $row): array {
                    $sent = (int) $row['sent'];
                    $delivered = (int) $row['delivered'];
                    $read = (int) $row['read'];
                    $row['delivery_rate'] = $sent > 0 ? round((($delivered + $read) / $sent) * 100, 1) : 0.0;
                    $row['read_rate'] = $sent > 0 ? round(($read / $sent) * 100, 1) : 0.0;

                    return $row;
                })
                ->all();
        }, $normalized['tenant'] !== '' ? $normalized['tenant'] : null);

        if ($normalized['q'] !== '') {
            $q = strtolower($normalized['q']);
            $rows = $rows->filter(fn ($r) => str_contains(strtolower(($r['tenant_name'] ?? '').($r['phone'] ?? '').($r['display_name'] ?? '')), $q))->values();
        }

        $kpi = [
            'lines' => $rows->count(),
            'green' => $rows->filter(fn ($r) => strtoupper((string) ($r['quality_rating'] ?? '')) === 'GREEN')->count(),
            'yellow' => $rows->filter(fn ($r) => strtoupper((string) ($r['quality_rating'] ?? '')) === 'YELLOW')->count(),
            'red' => $rows->filter(fn ($r) => strtoupper((string) ($r['quality_rating'] ?? '')) === 'RED')->count(),
            'failed_messages' => (int) $rows->sum('failed'),
        ];

        $sorted = AdminListQuery::sortRows(
            $rows,
            $normalized['sort'],
            $normalized['direction'],
            ['delivery_rate', 'read_rate', 'failed', 'tenant_name', 'sent'],
            'delivery_rate',
        );

        $paginator = new LengthAwarePaginator(
            $sorted->forPage(max(1, $page), $perPage)->values(),
            $sorted->count(),
            $perPage,
            max(1, $page),
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter($normalized)],
        );

        return ['items' => $paginator, 'filters' => $normalized, 'kpi' => $kpi];
    }

    public function normalizeDays(int|string|null $days): int
    {
        $value = (int) $days;
        if (! in_array($value, self::DAYS_PRESETS, true)) {
            return 30;
        }

        return $value;
    }

    /**
     * One aggregate per tenant: outbound message status counts by WhatsApp line.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function lineRowsForCurrentTenant(Carbon $since): Collection
    {
        $statsByLine = $this->outboundStatsByLineId($since);

        return WhatsappLine::query()
            ->orderByDesc('is_default')
            ->get()
            ->map(function (WhatsappLine $line) use ($statsByLine): array {
                $stats = $statsByLine[(int) $line->id] ?? [
                    'sent' => 0,
                    'delivered' => 0,
                    'read' => 0,
                    'failed' => 0,
                ];

                return [
                    'line_id' => $line->id,
                    'phone' => (string) $line->phone,
                    'display_name' => (string) ($line->display_name ?? ''),
                    'quality_rating' => (string) ($line->quality_rating ?? 'UNKNOWN'),
                    'messaging_limit_tier' => (string) ($line->messaging_limit_tier ?? '-'),
                    'is_default' => (bool) $line->is_default,
                    'sent' => (int) $stats['sent'],
                    'delivered' => (int) $stats['delivered'],
                    'read' => (int) $stats['read'],
                    'failed' => (int) $stats['failed'],
                ];
            })
            ->values();
    }

    /**
     * @return array<int, array{sent: int, delivered: int, read: int, failed: int}>
     */
    private function outboundStatsByLineId(Carbon $since): array
    {
        $sentStatuses = [
            MessageStatus::Sent->value,
            MessageStatus::Delivered->value,
            MessageStatus::Read->value,
            MessageStatus::Failed->value,
        ];

        $rows = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('messages.direction', MessageDirection::Outbound->value)
            ->where('messages.created_at', '>=', $since)
            ->whereIn('messages.status', $sentStatuses)
            ->groupBy('conversations.whatsapp_line_id')
            ->selectRaw(
                'conversations.whatsapp_line_id as line_id,
                 COUNT(*) as sent_count,
                 SUM(CASE WHEN messages.status = ? THEN 1 ELSE 0 END) as delivered_count,
                 SUM(CASE WHEN messages.status = ? THEN 1 ELSE 0 END) as read_count,
                 SUM(CASE WHEN messages.status = ? THEN 1 ELSE 0 END) as failed_count',
                [
                    MessageStatus::Delivered->value,
                    MessageStatus::Read->value,
                    MessageStatus::Failed->value,
                ],
            )
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $lineId = (int) ($row->line_id ?? 0);
            if ($lineId <= 0) {
                continue;
            }
            $out[$lineId] = [
                'sent' => (int) ($row->sent_count ?? 0),
                'delivered' => (int) ($row->delivered_count ?? 0),
                'read' => (int) ($row->read_count ?? 0),
                'failed' => (int) ($row->failed_count ?? 0),
            ];
        }

        return $out;
    }
}
