<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services;

use App\Domains\Alerts\Services\AlertDispatcher;
use App\Domains\Templates\Enums\TemplateStatus;
use App\Enums\MessageDirection;
use App\Models\Message;
use App\Models\Template;
use App\Models\WaHealthAlert;
use App\Models\WaHealthSnapshot;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatsAppHealthAlertService
{
    private function centralSchema(): \Illuminate\Database\Schema\Builder
    {
        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        return Schema::connection($connection);
    }

    public function recordLine(WhatsappLine $line, string $tenantId, ?string $previousQuality = null): void
    {
        if (! $this->centralSchema()->hasTable('wa_health_snapshots')) {
            return;
        }

        $date = now()->toDateString();
        $tier = (string) ($line->messaging_limit_tier ?? '');
        $tierLimit = WhatsAppHealthTierHelper::dailyLimit($tier, $line->status?->value ?? 'ACTIVE');
        $usage24h = $this->usageForLine($line);
        $quality = (string) ($line->quality_rating ?? '');

        $previous = WaHealthSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('whatsapp_line_id', $line->id)
            ->where('snapshot_date', '<', $date)
            ->orderByDesc('snapshot_date')
            ->first();

        $beforeQuality = $previousQuality ?? $previous?->quality_rating;

        WaHealthSnapshot::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'whatsapp_line_id' => $line->id,
                'snapshot_date' => $date,
            ],
            [
                'phone' => (string) $line->phone,
                'quality_rating' => $quality !== '' ? $quality : null,
                'messaging_limit_tier' => $tier !== '' ? $tier : null,
                'line_status' => $line->status?->value ?? null,
                'tier_limit' => $tierLimit,
                'usage_24h' => $usage24h,
            ]
        );

        $this->maybeAlertQualityChange($tenantId, $line, $beforeQuality, $quality);
        $this->maybeAlertLimitPressure($tenantId, $line, $tierLimit, $usage24h);
    }

    public function scanTemplateAlerts(string $tenantId): int
    {
        if (! $this->centralSchema()->hasTable('wa_health_alerts')) {
            return 0;
        }

        $created = 0;
        $staleBefore = now()->subHours(WhatsAppHealthTierHelper::STALE_PENDING_HOURS);

        Template::query()
            ->orderBy('id')
            ->chunkById(200, function ($templates) use (&$created, $tenantId, $staleBefore): void {
                foreach ($templates as $template) {
                    $status = WhatsAppHealthTierHelper::normalizeTemplateStatus(
                        $template->status instanceof TemplateStatus
                            ? $template->status->value
                            : (string) $template->status
                    );

                    if (WhatsAppHealthTierHelper::isRejectedTemplateStatus($status)) {
                        $this->upsertAlert([
                            'tenant_id' => $tenantId,
                            'whatsapp_line_id' => $template->whatsapp_line_id,
                            'template_id' => $template->id,
                            'alert_type' => 'template_rejected',
                            'severity' => 'critical',
                            'title' => 'Template rejected or disabled',
                            'body' => trim(($template->name ?? 'Template').' — '.$status
                                .(! empty($template->rejection_reason) ? "\n".$template->rejection_reason : '')),
                            'dedupe_key' => 'tpl_reject:'.$tenantId.':'.$template->id.':'.$status,
                        ]);
                        $created++;
                    } elseif (
                        WhatsAppHealthTierHelper::isPendingTemplateStatus($status)
                        && $template->updated_at !== null
                        && $template->updated_at->lte($staleBefore)
                    ) {
                        $this->upsertAlert([
                            'tenant_id' => $tenantId,
                            'whatsapp_line_id' => $template->whatsapp_line_id,
                            'template_id' => $template->id,
                            'alert_type' => 'template_pending_stale',
                            'severity' => 'warning',
                            'title' => 'Template pending over 24h',
                            'body' => ($template->name ?? 'Template').' still '.$status,
                            'dedupe_key' => 'tpl_pending_stale:'.$tenantId.':'.$template->id,
                        ]);
                        $created++;
                    }
                }
            });

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertAlert(array $data): void
    {
        if (! $this->centralSchema()->hasTable('wa_health_alerts')) {
            return;
        }

        $payload = array_merge($data, [
            'occurred_at' => $data['occurred_at'] ?? now(),
            'is_read' => false,
        ]);

        $dedupe = $data['dedupe_key'] ?? null;
        if ($dedupe === null) {
            $alert = WaHealthAlert::query()->create($payload);
            $this->maybeDispatchCriticalWebhook($alert, true);

            return;
        }

        $existing = WaHealthAlert::query()->where('dedupe_key', $dedupe)->first();
        $alert = WaHealthAlert::query()->updateOrCreate(
            ['dedupe_key' => $dedupe],
            $payload
        );
        $this->maybeDispatchCriticalWebhook($alert, $existing === null);
    }

    private function maybeAlertQualityChange(string $tenantId, WhatsappLine $line, ?string $before, ?string $after): void
    {
        $b = strtoupper(trim((string) $before));
        $a = strtoupper(trim((string) $after));
        if ($a === '' || $b === $a) {
            return;
        }

        if ($a === 'RED') {
            $this->upsertAlert([
                'tenant_id' => $tenantId,
                'whatsapp_line_id' => $line->id,
                'alert_type' => 'quality_red',
                'severity' => 'critical',
                'title' => 'WhatsApp quality is RED',
                'body' => ($line->display_name ?: $line->phone).' dropped to RED.',
                'dedupe_key' => 'quality_red:'.$tenantId.':'.$line->id.':'.now()->toDateString(),
            ]);

            try {
                app(AlertDispatcher::class)->phoneQualityChanged($line, $before, (string) $line->messaging_limit_tier);
            } catch (\Throwable) {
            }
        } elseif ($a === 'YELLOW') {
            $this->upsertAlert([
                'tenant_id' => $tenantId,
                'whatsapp_line_id' => $line->id,
                'alert_type' => 'quality_yellow',
                'severity' => 'warning',
                'title' => 'WhatsApp quality is YELLOW',
                'body' => ($line->display_name ?: $line->phone).' is YELLOW.',
                'dedupe_key' => 'quality_yellow:'.$tenantId.':'.$line->id.':'.now()->toDateString(),
            ]);

            try {
                app(AlertDispatcher::class)->phoneQualityChanged($line, $before, (string) $line->messaging_limit_tier);
            } catch (\Throwable) {
            }
        }
    }

    private function maybeAlertLimitPressure(string $tenantId, WhatsappLine $line, int $tierLimit, int $usage24h): void
    {
        $pct = WhatsAppHealthTierHelper::usagePercent($usage24h, $tierLimit);
        if ($pct === null || $pct < 80) {
            return;
        }

        $this->upsertAlert([
            'tenant_id' => $tenantId,
            'whatsapp_line_id' => $line->id,
            'alert_type' => 'limit_high',
            'severity' => $pct >= 95 ? 'critical' : 'warning',
            'title' => 'Messaging limit above '.(int) $pct.'%',
            'body' => ($line->display_name ?: $line->phone)." used {$usage24h} of {$tierLimit} outbound messages (24h proxy).",
            'dedupe_key' => 'limit_high:'.$tenantId.':'.$line->id.':'.now()->toDateString(),
        ]);
    }

    private function usageForLine(WhatsappLine $line): int
    {
        try {
            return (int) Message::query()
                ->where('direction', MessageDirection::Outbound)
                ->whereHas('conversation', fn ($q) => $q->where('whatsapp_line_id', $line->id))
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function maybeDispatchCriticalWebhook(WaHealthAlert $alert, bool $isNew): void
    {
        if (! $isNew || ($alert->severity ?? '') !== 'critical') {
            return;
        }

        $url = trim((string) config('services.wa_health.alert_webhook_url', ''));
        if ($url === '') {
            return;
        }

        try {
            Http::timeout(8)->post($url, [
                'id' => $alert->id,
                'tenant_id' => $alert->tenant_id,
                'whatsapp_line_id' => $alert->whatsapp_line_id,
                'alert_type' => $alert->alert_type,
                'severity' => $alert->severity,
                'title' => $alert->title,
                'body' => $alert->body,
                'occurred_at' => $alert->occurred_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('WA health critical webhook failed', ['error' => $e->getMessage()]);
        }
    }
}
