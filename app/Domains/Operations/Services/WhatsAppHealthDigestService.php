<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services;

use App\Domains\Admin\Services\WhatsappHealthAdminService;
use App\Domains\Alerts\Services\AlertDispatcher;
use App\Models\WaHealthAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class WhatsAppHealthDigestService
{
    public function __construct(
        private readonly WhatsappHealthAdminService $fleet,
        private readonly AlertDispatcher $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildSummary(): array
    {
        $fleet = $this->fleet->fleet([], 1, 5000);
        $kpi = $fleet['kpi'];

        $unread = 0;
        $critical7d = 0;
        $recentAlerts = [];

        if (Schema::connection((string) config('tenancy.database.central_connection', config('database.default')))->hasTable('wa_health_alerts')) {
            $unread = WaHealthAlert::query()->where('is_read', false)->count();
            $critical7d = WaHealthAlert::query()
                ->where('severity', 'critical')
                ->where('occurred_at', '>=', now()->subDays(7))
                ->count();
            $recentAlerts = WaHealthAlert::query()
                ->latest('occurred_at')
                ->limit(15)
                ->get()
                ->map(fn (WaHealthAlert $a): array => [
                    'title' => $a->title,
                    'body' => $a->body,
                    'severity' => $a->severity,
                    'type' => $a->alert_type,
                    'tenant_id' => $a->tenant_id,
                    'occurred_at' => $a->occurred_at?->format('d M Y H:i'),
                ])
                ->all();
        }

        $needsAttention = $unread > 0
            || ($kpi['red'] ?? 0) > 0
            || ($kpi['yellow'] ?? 0) > 0
            || $critical7d > 0;

        return [
            'generated_at' => now()->format('d M Y h:i A'),
            'needs_attention' => $needsAttention,
            'tenants_scanned' => collect($fleet['items']->items())->pluck('tenant_id')->unique()->count(),
            'lines_checked' => $kpi['lines'] ?? 0,
            'quality_green' => $kpi['green'] ?? 0,
            'quality_yellow' => $kpi['yellow'] ?? 0,
            'quality_red' => $kpi['red'] ?? 0,
            'quality_drops' => $kpi['red'] ?? 0,
            'tier_changes' => 0,
            'failed_messages' => $kpi['failed_messages'] ?? 0,
            'unread_alerts' => $unread,
            'critical_alerts_7d' => $critical7d,
            'recent_alerts' => $recentAlerts,
            'summary' => $needsAttention
                ? 'Attention needed: unread alerts or degraded line quality detected.'
                : 'Fleet looks healthy — no critical attention items.',
            'notes' => 'Daily WhatsApp Health digest (legacy parity). Critical alerts also POST to WA_HEALTH_ALERT_WEBHOOK_URL when set.',
            'health_url' => route('admin.whatsapp-health.index'),
        ];
    }

    public function sendDailyDigest(bool $force = false): int
    {
        $dayKey = 'wa_health_digest_sent:'.now()->toDateString();
        if (! $force && ! Cache::add($dayKey, 1, now()->endOfDay())) {
            return 0;
        }

        $summary = $this->buildSummary();
        $this->alerts->whatsappHealthDigest($summary);

        return 1;
    }
}
