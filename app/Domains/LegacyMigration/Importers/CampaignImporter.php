<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;

final class CampaignImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'campaigns';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('new_campaigns')) {
            return;
        }

        $rows = $this->legacy->db()->table('new_campaigns')
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $legacyId = (int) $row->id;
            $name = trim((string) ($row->name ?? 'Untitled Campaign'));
            $existingId = $ids->getInt('campaign', $legacyId);
            $existing = $existingId ? Campaign::query()->find($existingId) : null;

            if ($existing === null) {
                $existing = Campaign::query()->where('name', $name)->first();
            }

            if ($dryRun) {
                $report->bump($this->key(), $existing ? 'updated' : 'created');

                continue;
            }

            $templateId = isset($row->template_id) && $row->template_id
                ? $ids->getInt('template', (int) $row->template_id)
                : null;
            $audienceId = isset($row->mail_list_id) && $row->mail_list_id
                ? $ids->getInt('list', (int) $row->mail_list_id)
                : null;
            $createdBy = isset($row->team_member_id) && $row->team_member_id
                ? $ids->getInt('team_member', (int) $row->team_member_id)
                : null;

            $attributes = [
                'name' => $name,
                'status' => $this->mapStatus($row->status ?? null),
                'audience_id' => $audienceId,
                'whatsapp_line_id' => $this->resolveLineId($row, $ids),
                'template_id' => $templateId,
                'scheduled_at' => $row->schedule_time ?? null,
                'timezone' => 'Asia/Kolkata',
                'total_recipients' => (int) ($row->total_to_send ?? $row->total_recipients ?? 0),
                'total_delivered' => (int) ($row->sent ?? $row->delivered ?? $row->total_delivered ?? 0),
                'total_failed' => (int) ($row->failed ?? $row->total_failed ?? 0),
                'total_read' => (int) ($row->read ?? $row->total_read ?? 0),
                'total_response' => (int) ($row->response ?? $row->total_response ?? 0),
                'total_unsubscribed' => (int) ($row->unsubscribed ?? $row->total_unsubscribed ?? 0),
                'created_by' => $createdBy,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();
                $campaign = $existing;
                $report->bump($this->key(), 'updated');
            } else {
                $campaign = Campaign::query()->create($attributes);
                $report->bump($this->key(), 'created');
            }

            $ids->put('campaign', $legacyId, $campaign->id);
            $this->importRecipients($customer->id, $legacyId, $campaign, $report);
            $this->refreshCampaignTotals($campaign);
        }
    }

    private function importRecipients(
        int $legacyCustomerId,
        int $legacyCampaignId,
        Campaign $campaign,
        MigrationReport $report,
    ): void {
        if (! $this->legacy->tableExists('inboxes')) {
            return;
        }

        $chunk = (int) config('legacy-migration.chunks.campaign_recipients', 500);
        $query = $this->legacy->db()->table('inboxes');

        if ($this->legacy->hasColumn('inboxes', 'campaign_id')) {
            $query->where('campaign_id', $legacyCampaignId);
        } elseif ($this->legacy->hasColumn('inboxes', 'new_campaign_id')) {
            $query->where('new_campaign_id', $legacyCampaignId);
        } else {
            $report->warn('Legacy [inboxes] has no campaign_id column; recipient import skipped.');

            return;
        }

        if ($this->legacy->hasColumn('inboxes', 'customer_id')) {
            $query->where('customer_id', $legacyCustomerId);
        }

        $query->orderBy('id')->chunkById($chunk, function ($rows) use ($campaign, $report): void {
            foreach ($rows as $row) {
                // Reply rows are not outbound campaign deliveries.
                if ($this->truthy($row->is_response_message ?? null)) {
                    $report->bump('campaign_recipients', 'skipped');

                    continue;
                }

                $phone = $this->extractPhone($row);
                if ($phone === null) {
                    $report->bump('campaign_recipients', 'skipped');

                    continue;
                }

                $status = $this->mapRecipientStatus($row);
                $contactId = Contact::query()->where('phone', $phone)->value('id');

                $existing = CampaignRecipient::query()
                    ->where('campaign_id', $campaign->id)
                    ->where('contact_phone', $phone)
                    ->first();

                $attributes = [
                    'campaign_id' => $campaign->id,
                    'contact_id' => $contactId,
                    'contact_phone' => $phone,
                    'status' => $status,
                    'sent_at' => $this->nullableDate($row->sent_at ?? $row->created_at ?? null),
                    'delivered_at' => $this->nullableDate($row->delivered_at ?? null),
                    'read_at' => $this->nullableDate($row->read_at ?? $row->responded_at ?? null),
                    'failed_at' => $this->nullableDate($row->failed_at ?? null),
                    'failure_reason' => $row->failed_reason ?? $row->failure_reason ?? null,
                    'message_id' => filled($row->msg_id ?? null) ? (string) $row->msg_id : null,
                    'variable_values' => [
                        'legacy_inbox_id' => (int) $row->id,
                    ],
                ];

                if ($existing !== null) {
                    // Keep the "highest" status if the same phone appears more than once.
                    if ($this->statusRank($status) >= $this->statusRank($existing->status)) {
                        $existing->forceFill($attributes)->save();
                    }
                    $report->bump('campaign_recipients', 'updated');
                } else {
                    CampaignRecipient::query()->create($attributes);
                    $report->bump('campaign_recipients', 'created');
                }
            }
        });
    }

    private function refreshCampaignTotals(Campaign $campaign): void
    {
        $stats = CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->selectRaw('
                COUNT(*) as total,
                COUNT(CASE WHEN status = ? THEN 1 END) as delivered,
                COUNT(CASE WHEN status = ? THEN 1 END) as failed,
                COUNT(CASE WHEN status = ? THEN 1 END) as `read`,
                COUNT(CASE WHEN status = ? THEN 1 END) as response,
                COUNT(CASE WHEN status = ? THEN 1 END) as unsubscribed
            ', [
                CampaignRecipientStatus::Delivered->value,
                CampaignRecipientStatus::Failed->value,
                CampaignRecipientStatus::Read->value,
                CampaignRecipientStatus::Response->value,
                CampaignRecipientStatus::Unsubscribed->value,
            ])
            ->first();

        $total = (int) ($stats->total ?? 0);
        if ($total === 0) {
            return;
        }

        $read = (int) ($stats->read ?? 0);
        $response = (int) ($stats->response ?? 0);
        $deliveredExclusive = (int) ($stats->delivered ?? 0);

        $campaign->forceFill([
            'total_recipients' => $total,
            // Progressive: read/response count as delivered for overview chips.
            'total_delivered' => $deliveredExclusive + $read + $response,
            'total_failed' => (int) ($stats->failed ?? 0),
            'total_read' => $read,
            'total_response' => $response,
            'total_unsubscribed' => (int) ($stats->unsubscribed ?? 0),
        ])->save();
    }

    private function extractPhone(object $row): ?string
    {
        foreach (['to', 'phone', 'to_phone', 'receiver', 'subscriber_phone', 'contact_phone', 'whatsapp'] as $column) {
            if (! isset($row->{$column}) || ! filled($row->{$column})) {
                continue;
            }

            $normalized = PhoneNormalizer::normalize((string) $row->{$column});
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    private function mapRecipientStatus(object $row): CampaignRecipientStatus
    {
        $statusText = strtolower(trim((string) ($row->status ?? '')));

        if ($this->truthy($row->is_failed ?? null) || in_array($statusText, ['failed', 'fail', 'error'], true)) {
            return CampaignRecipientStatus::Failed;
        }
        if (filled($row->unsubscribed_at ?? null) || $statusText === 'unsubscribed') {
            return CampaignRecipientStatus::Unsubscribed;
        }
        if ($this->truthy($row->is_response ?? null) || $statusText === 'response') {
            return CampaignRecipientStatus::Response;
        }
        if ($this->truthy($row->is_read ?? null) || filled($row->read_at ?? null) || $statusText === 'read') {
            return CampaignRecipientStatus::Read;
        }
        if ($this->truthy($row->is_delivered ?? null) || filled($row->delivered_at ?? null) || $statusText === 'delivered') {
            return CampaignRecipientStatus::Delivered;
        }
        if ($this->truthy($row->is_sent ?? null) || filled($row->sent_at ?? null) || in_array($statusText, ['sent', 'success', '1'], true)) {
            return CampaignRecipientStatus::Sent;
        }

        return CampaignRecipientStatus::Pending;
    }

    private function truthy(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return in_array($value, [true, 1, '1', 'true', 'yes'], true);
    }

    private function statusRank(CampaignRecipientStatus|string|null $status): int
    {
        $value = $status instanceof CampaignRecipientStatus ? $status : CampaignRecipientStatus::tryFrom((string) $status);

        return match ($value) {
            CampaignRecipientStatus::Failed => 60,
            CampaignRecipientStatus::Unsubscribed => 50,
            CampaignRecipientStatus::Response => 40,
            CampaignRecipientStatus::Read => 30,
            CampaignRecipientStatus::Delivered => 20,
            CampaignRecipientStatus::Sent => 10,
            default => 0,
        };
    }

    private function nullableDate(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00' || $value === '1970-01-01 00:00:00') {
            return null;
        }

        return $value;
    }

    private function resolveLineId(object $row, MigrationIdMap $ids): ?int
    {
        if (isset($row->new_contact_id) && $row->new_contact_id) {
            $mapped = $ids->getInt('line', (int) $row->new_contact_id);
            if ($mapped !== null) {
                return $mapped;
            }
        }

        $lines = $ids->all()['line'] ?? [];
        if (count($lines) === 1) {
            return (int) reset($lines);
        }

        return null;
    }

    private function mapStatus(mixed $status): CampaignStatus
    {
        $value = strtolower((string) $status);

        return match (true) {
            in_array($value, ['done', 'completed', 'sent', 'finished'], true) => CampaignStatus::Completed,
            in_array($value, ['running', 'sending', 'active'], true) => CampaignStatus::Sending,
            in_array($value, ['scheduled', 'queue', 'queued'], true) => CampaignStatus::Scheduled,
            in_array($value, ['paused', 'pause'], true) => CampaignStatus::Paused,
            in_array($value, ['cancelled', 'canceled'], true) => CampaignStatus::Cancelled,
            default => CampaignStatus::Draft,
        };
    }
}
