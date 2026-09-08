<?php

declare(strict_types=1);

namespace App\Domains\Admin\Services;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\WhatsappLine;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class WhatsappHealthAdminService
{
    public function __construct(
        private readonly CrossTenantScanner $scanner,
    ) {}

    /**
     * @param  array{q?: string, tenant_id?: string, quality?: string}  $filters
     * @return array{kpi: array<string, int>, items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function fleet(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $filters = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'tenant' => trim((string) ($filters['tenant'] ?? $filters['tenant_id'] ?? '')),
            'quality' => trim((string) ($filters['quality'] ?? '')),
        ];

        $rows = $this->scanner->map(function (): array {
            return WhatsappLine::query()
                ->orderByDesc('is_default')
                ->get()
                ->map(function (WhatsappLine $line): array {
                    $conversationIds = \App\Models\Conversation::query()
                        ->where('whatsapp_line_id', $line->id)
                        ->pluck('id');

                    $base = Message::query()->whereIn('conversation_id', $conversationIds);
                    $delivered = (clone $base)->where('status', MessageStatus::Delivered)->count();
                    $read = (clone $base)->where('status', MessageStatus::Read)->count();
                    $failed = (clone $base)->where('status', MessageStatus::Failed)->count();

                    return [
                        'line_id' => $line->id,
                        'phone' => (string) $line->phone,
                        'display_name' => (string) ($line->display_name ?? ''),
                        'quality_rating' => (string) ($line->quality_rating ?? 'UNKNOWN'),
                        'messaging_limit_tier' => (string) ($line->messaging_limit_tier ?? '—'),
                        'is_default' => (bool) $line->is_default,
                        'delivered' => $delivered,
                        'read' => $read,
                        'failed' => $failed,
                    ];
                })->all();
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
     * @param  array{q?: string, tenant_id?: string}  $filters
     * @return array{items: LengthAwarePaginator<int, array<string, mixed>>, filters: array<string, string>}
     */
    public function messagePerformance(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $fleet = $this->fleet($filters, $page, $perPage);
        $mapped = collect($fleet['items']->items())->map(function (array $row): array {
            $sent = (int) $row['delivered'] + (int) $row['read'] + (int) $row['failed'];
            $row['sent'] = $sent;
            $row['delivery_rate'] = $sent > 0 ? round((((int) $row['delivered'] + (int) $row['read']) / $sent) * 100, 1) : 0;
            $row['read_rate'] = $sent > 0 ? round(((int) $row['read'] / $sent) * 100, 1) : 0;

            return $row;
        });

        $paginator = new LengthAwarePaginator(
            $mapped->values(),
            $fleet['items']->total(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => array_filter($fleet['filters'])],
        );

        return ['items' => $paginator, 'filters' => $fleet['filters'], 'kpi' => $fleet['kpi']];
    }
}
