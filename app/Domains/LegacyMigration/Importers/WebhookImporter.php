<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEventType;
use App\Enums\WebhookSubscriptionStatus;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Models\WhatsappLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Legacy outbound webhooks → 2.0 Webhook List + Delivery Logs.
 *
 * webhook_settings → webhook_subscriptions
 * webhook_logs     → webhook_deliveries
 */
final class WebhookImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'webhooks';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        $this->importSubscriptions($customer, $ids, $report, $dryRun);
        $this->importDeliveries($customer, $ids, $report, $dryRun);
    }

    private function importSubscriptions(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('webhook_settings')) {
            return;
        }

        $rows = $this->legacy->db()->table('webhook_settings')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $url = trim((string) ($row->url ?? ''));
            if ($url === '') {
                $report->bump($this->key(), 'skipped');

                continue;
            }

            $lineId = $this->resolveLineId($row, $ids);
            $listId = $this->resolveListId($row, $ids);
            $events = $this->normalizeEvents($row->events ?? null);
            $status = $this->mapSubscriptionStatus($row->status ?? null);
            $secret = trim((string) ($row->secret_key ?? ''));
            if ($secret === '') {
                $secret = Str::random(40);
            }

            $existingId = $ids->getInt('webhook_subscription', $legacyId);
            $existing = $existingId
                ? WebhookSubscription::query()->find($existingId)
                : WebhookSubscription::query()
                    ->where('url', $url)
                    ->when(
                        $lineId !== null,
                        fn ($q) => $q->where('whatsapp_line_id', $lineId),
                        fn ($q) => $q->whereNull('whatsapp_line_id'),
                    )
                    ->first();

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $attributes = [
                'whatsapp_line_id' => $lineId,
                'url' => $url,
                'description' => filled($row->description ?? null) ? (string) $row->description : null,
                'secret_key' => $secret,
                'events' => $events,
                'status' => $status,
                'audience_list_id' => $listId,
                'last_triggered_at' => $this->sanitizeDateTime($row->last_triggered_at ?? null),
            ];

            if ($existing !== null) {
                // Keep an existing secret if legacy row has an empty one (already generated above only when empty).
                if ($secret === '' || ($row->secret_key ?? null) === null || (string) ($row->secret_key ?? '') === '') {
                    unset($attributes['secret_key']);
                }
                $existing->forceFill($attributes)->save();
                $subscription = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $subscription = WebhookSubscription::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('webhook_subscription', $legacyId, $subscription->id);
        }
    }

    private function importDeliveries(
        LegacyCustomerSnapshot $customer,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        if (! $this->legacy->tableExists('webhook_logs')) {
            return;
        }

        $chunk = (int) config('legacy-migration.chunks.webhook_logs', 300);

        $this->legacy->db()->table('webhook_logs')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($ids, $report, $dryRun): void {
                foreach ($rows as $row) {
                    $this->importOneDelivery($row, $ids, $report, $dryRun);
                }
            });
    }

    private function importOneDelivery(
        object $row,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
    ): void {
        $legacyId = (int) $row->id;
        $legacyWebhookId = isset($row->webhook_id) ? (int) $row->webhook_id : 0;

        $subscriptionId = $legacyWebhookId > 0
            ? $ids->getInt('webhook_subscription', $legacyWebhookId)
            : null;

        if ($subscriptionId === null && filled($row->webhook_url ?? null)) {
            $subscriptionId = WebhookSubscription::query()
                ->where('url', (string) $row->webhook_url)
                ->value('id');
            $subscriptionId = $subscriptionId !== null ? (int) $subscriptionId : null;
        }

        if ($subscriptionId === null) {
            $report->bump('webhook_logs', 'skipped');

            return;
        }

        $correlationId = 'legacy-webhook-log-'.$legacyId;
        $mappedId = $ids->getInt('webhook_delivery', $legacyId);
        $existing = $mappedId
            ? WebhookDelivery::query()->find($mappedId)
            : WebhookDelivery::query()->where('correlation_id', $correlationId)->first();

        if ($dryRun) {
            $report->bump('webhook_logs', $existing ? 'updated' : 'created');

            return;
        }

        $lineId = $this->resolveLineId($row, $ids);
        $eventType = $this->normalizeEventType((string) ($row->event_type ?? 'new_lead'));
        $payload = $this->decodePayload($row->payload ?? null);

        $attributes = [
            'webhook_subscription_id' => $subscriptionId,
            'whatsapp_line_id' => $lineId,
            'event_type' => $eventType,
            'correlation_id' => $correlationId,
            'payload' => $payload,
            'response_status' => isset($row->response_status) ? (int) $row->response_status : null,
            'response_body' => isset($row->response_body) ? Str::limit((string) $row->response_body, 65000, '…') : null,
            'error_message' => isset($row->error_message) ? Str::limit((string) $row->error_message, 65000, '…') : null,
            'status' => $this->mapDeliveryStatus($row->status ?? null, $row->response_status ?? null),
            'attempt_count' => 1,
            'sent_at' => $this->sanitizeDateTime($row->sent_at ?? $row->created_at ?? null),
            'response_received_at' => $this->sanitizeDateTime($row->response_received_at ?? null),
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();
            $delivery = $existing;
            $report->bump('webhook_logs', 'updated');
        } else {
            $delivery = WebhookDelivery::query()->create($attributes);
            $report->bump('webhook_logs', 'created');
        }

        $ids->put('webhook_delivery', $legacyId, $delivery->id);
    }

    private function resolveLineId(object $row, MigrationIdMap $ids): ?int
    {
        $legacyLineId = null;
        foreach (['new_contact_id', 'whatsapp_line_id', 'line_id'] as $column) {
            if (isset($row->{$column}) && (int) $row->{$column} > 0) {
                $legacyLineId = (int) $row->{$column};
                break;
            }
        }

        if ($legacyLineId !== null) {
            $mapped = $ids->getInt('line', $legacyLineId);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        return WhatsappLine::query()->orderByDesc('is_default')->orderBy('id')->value('id');
    }

    private function resolveListId(object $row, MigrationIdMap $ids): ?int
    {
        if (! isset($row->audience_list_id) || (int) $row->audience_list_id <= 0) {
            return null;
        }

        return $ids->getInt('list', (int) $row->audience_list_id);
    }

    /**
     * @return list<string>
     */
    private function normalizeEvents(mixed $raw): array
    {
        $decoded = $raw;
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
            } else {
                $decoded = preg_split('/\s*,\s*/', $raw) ?: [];
            }
        }

        if (! is_array($decoded)) {
            return [WebhookEventType::NewLead->value];
        }

        $events = [];
        foreach ($decoded as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $normalized = $this->normalizeEventType((string) $item);
            if ($normalized !== '') {
                $events[] = $normalized;
            }
        }

        $events = array_values(array_unique($events));

        return $events !== [] ? $events : [WebhookEventType::NewLead->value];
    }

    private function normalizeEventType(string $raw): string
    {
        $value = strtolower(trim($raw));
        $value = str_replace([' ', '-'], '_', $value);

        return match ($value) {
            'new_lead', 'newlead', 'lead', 'lead_created', 'new_contact', 'contact_created' => WebhookEventType::NewLead->value,
            default => $value !== '' ? $value : WebhookEventType::NewLead->value,
        };
    }

    private function mapSubscriptionStatus(mixed $raw): WebhookSubscriptionStatus
    {
        $value = strtolower(trim((string) ($raw ?? 'active')));

        return in_array($value, ['inactive', 'disabled', '0', 'false', 'off'], true)
            ? WebhookSubscriptionStatus::Inactive
            : WebhookSubscriptionStatus::Active;
    }

    private function mapDeliveryStatus(mixed $raw, mixed $responseStatus): WebhookDeliveryStatus
    {
        $value = strtolower(trim((string) ($raw ?? '')));

        if (in_array($value, ['sent', 'success', 'delivered', 'ok', 'completed'], true)) {
            return WebhookDeliveryStatus::Sent;
        }

        if (in_array($value, ['retrying', 'retry', 'requeued'], true)) {
            return WebhookDeliveryStatus::Retrying;
        }

        if (in_array($value, ['pending', 'queued'], true)) {
            return WebhookDeliveryStatus::Pending;
        }

        if (in_array($value, ['failed', 'error', 'failure'], true)) {
            return WebhookDeliveryStatus::Failed;
        }

        if (is_numeric($responseStatus)) {
            $code = (int) $responseStatus;

            return $code >= 200 && $code < 300
                ? WebhookDeliveryStatus::Sent
                : WebhookDeliveryStatus::Failed;
        }

        return WebhookDeliveryStatus::Failed;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['raw' => Str::limit($raw, 20000, '…')];
    }

    private function sanitizeDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
