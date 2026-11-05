<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Support\AdminListQuery;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class RecentCampaignAdminService
{
    public function __construct(
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * @param  array{
     *     q?: string,
     *     tenant?: string,
     *     status?: string,
     *     date_from?: string,
     *     date_to?: string,
     *     sort?: string,
     *     direction?: string
     * }  $filters
     * @return array{
     *     items: LengthAwarePaginator<int, array<string, mixed>>,
     *     filters: array<string, string>,
     *     kpi: array{total: int, sending: int, completed: int, failed: int}
     * }
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $sort = (string) ($filters['sort'] ?? 'activity_at');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (! in_array($sort, ['activity_at', 'tenant_name', 'name', 'status', 'total_recipients', 'total_delivered', 'total_failed'], true)) {
            $sort = 'activity_at';
        }
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $filters = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'tenant' => trim((string) ($filters['tenant'] ?? $filters['tenant_id'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'sort' => $sort,
            'direction' => $direction,
        ];

        $rows = $this->scanner->map(function (): array {
            return Campaign::query()
                ->with(['template:id,name', 'whatsappLine:id,phone,display_name', 'creator:id,name'])
                ->where(function ($q): void {
                    // Prefer campaigns that actually moved beyond draft.
                    $q->where('status', '!=', CampaignStatus::Draft->value)
                        ->orWhereNotNull('started_at')
                        ->orWhereNotNull('completed_at');
                })
                ->orderByDesc('started_at')
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->map(function (Campaign $campaign): array {
                    $status = $campaign->status instanceof CampaignStatus
                        ? $campaign->status
                        : CampaignStatus::tryFrom((string) $campaign->status);

                    $activityAt = $campaign->completed_at
                        ?? $campaign->started_at
                        ?? $campaign->scheduled_at
                        ?? $campaign->updated_at
                        ?? $campaign->created_at;

                    return [
                        'campaign_id' => $campaign->id,
                        'name' => (string) $campaign->name,
                        'status' => $status?->value ?? (string) $campaign->status,
                        'status_label' => $status?->label() ?? (string) $campaign->status,
                        'template_name' => (string) ($campaign->template?->name ?? '-'),
                        'line_phone' => (string) ($campaign->whatsappLine?->phone ?? '-'),
                        'line_name' => (string) ($campaign->whatsappLine?->display_name ?? ''),
                        'audience' => $campaign->audienceName(),
                        'created_by' => (string) ($campaign->creator?->name ?? '-'),
                        'total_recipients' => (int) $campaign->total_recipients,
                        'total_delivered' => (int) $campaign->total_delivered,
                        'total_failed' => (int) $campaign->total_failed,
                        'total_read' => (int) $campaign->total_read,
                        'total_response' => (int) $campaign->total_response,
                        'started_at' => $campaign->started_at?->toIso8601String(),
                        'completed_at' => $campaign->completed_at?->toIso8601String(),
                        'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                        'activity_at' => $activityAt instanceof Carbon ? $activityAt->toIso8601String() : null,
                        'activity_ts' => $activityAt instanceof Carbon ? $activityAt->getTimestamp() : 0,
                    ];
                })
                ->all();
        }, $filters['tenant'] !== '' ? $filters['tenant'] : null);

        if ($filters['status'] !== '') {
            $status = strtolower($filters['status']);
            $rows = $rows->filter(fn (array $row): bool => strtolower((string) $row['status']) === $status)->values();
        }

        if ($filters['q'] !== '') {
            $q = strtolower($filters['q']);
            $rows = $rows->filter(function (array $row) use ($q): bool {
                $haystack = strtolower(implode(' ', [
                    $row['tenant_name'] ?? '',
                    $row['name'] ?? '',
                    $row['template_name'] ?? '',
                    $row['line_phone'] ?? '',
                    $row['line_name'] ?? '',
                    $row['created_by'] ?? '',
                ]));

                return str_contains($haystack, $q);
            })->values();
        }

        if ($filters['date_from'] !== '' || $filters['date_to'] !== '') {
            $from = $filters['date_from'] !== ''
                ? Carbon::parse($filters['date_from'], 'Asia/Kolkata')->startOfDay()->utc()
                : null;
            $to = $filters['date_to'] !== ''
                ? Carbon::parse($filters['date_to'], 'Asia/Kolkata')->endOfDay()->utc()
                : null;

            $rows = $rows->filter(function (array $row) use ($from, $to): bool {
                $ts = (int) ($row['activity_ts'] ?? 0);
                if ($ts <= 0) {
                    return false;
                }
                if ($from !== null && $ts < $from->getTimestamp()) {
                    return false;
                }
                if ($to !== null && $ts > $to->getTimestamp()) {
                    return false;
                }

                return true;
            })->values();
        }

        $sorted = match ($sort) {
            'activity_at' => $direction === 'asc'
                ? $rows->sortBy('activity_ts')->values()
                : $rows->sortByDesc('activity_ts')->values(),
            default => AdminListQuery::sortRows(
                $rows,
                $sort,
                $direction,
                ['tenant_name', 'name', 'status', 'total_recipients', 'total_delivered', 'total_failed'],
                'tenant_name',
            ),
        };

        $kpi = [
            'total' => $sorted->count(),
            'sending' => $sorted->where('status', CampaignStatus::Sending->value)->count(),
            'completed' => $sorted->where('status', CampaignStatus::Completed->value)->count(),
            'failed' => $sorted->where('status', CampaignStatus::Failed->value)->count(),
        ];

        $paginator = new LengthAwarePaginator(
            $sorted->forPage(max(1, $page), $perPage)->values(),
            $sorted->count(),
            $perPage,
            max(1, $page),
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter($filters)],
        );

        return [
            'items' => $paginator,
            'filters' => $filters,
            'kpi' => $kpi,
        ];
    }
}
