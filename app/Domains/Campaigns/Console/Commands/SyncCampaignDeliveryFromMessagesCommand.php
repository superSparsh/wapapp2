<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Console\Commands;

use App\Enums\CampaignRecipientStatus;
use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * When inbox messages already show Delivered/Read (wallet charged) but campaign
 * recipient rows stayed on Sent, sync recipient + campaign counters from message metadata.
 */
class SyncCampaignDeliveryFromMessagesCommand extends Command
{
    protected $signature = 'campaigns:sync-delivery-from-messages
                            {--tenant= : Tenant id (required unless tenancy already initialized)}
                            {--campaign= : Limit to one campaign id}
                            {--limit=2000 : Max messages to scan}
                            {--dry-run : Show actions without writing}';

    protected $description = 'Backfill campaign recipient delivery stats from delivered/read inbox messages';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $campaignFilter = $this->option('campaign') !== null && $this->option('campaign') !== ''
            ? (int) $this->option('campaign')
            : null;

        $tenantId = trim((string) $this->option('tenant'));
        $endedTenancy = false;

        if (! tenancy()->initialized) {
            if ($tenantId === '') {
                $this->error('Pass --tenant= when not inside an initialized tenancy context.');

                return self::FAILURE;
            }

            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                $this->error("Tenant not found: {$tenantId}");

                return self::FAILURE;
            }

            tenancy()->initialize($tenant);
            $endedTenancy = true;
        }

        try {
            $query = Message::query()
                ->where('direction', 'outbound')
                ->whereIn('status', [
                    MessageStatus::Delivered->value,
                    MessageStatus::Read->value,
                    MessageStatus::Failed->value,
                ])
                ->whereNotNull('metadata')
                ->orderByDesc('id')
                ->limit($limit);

            $updated = 0;
            $skipped = 0;
            $skipReasons = [
                'no_recipient_meta' => 0,
                'campaign_filter' => 0,
                'recipient_missing' => 0,
                'already_synced' => 0,
                'no_transition' => 0,
            ];

            foreach ($query->cursor() as $message) {
                $meta = is_array($message->metadata) ? $message->metadata : [];
                $recipientId = (int) ($meta['campaign_recipient_id'] ?? 0);
                $metaCampaignId = (int) ($meta['campaign_id'] ?? 0);

                if ($recipientId <= 0) {
                    $skipped++;
                    $skipReasons['no_recipient_meta']++;

                    continue;
                }

                // Only trust metadata campaign_id when present; otherwise resolve via recipient row.
                if ($campaignFilter !== null && $metaCampaignId > 0 && $metaCampaignId !== $campaignFilter) {
                    $skipped++;
                    $skipReasons['campaign_filter']++;

                    continue;
                }

                $recipient = CampaignRecipient::query()->find($recipientId);
                if ($recipient === null) {
                    $skipped++;
                    $skipReasons['recipient_missing']++;

                    continue;
                }

                if ($campaignFilter !== null && (int) $recipient->campaign_id !== $campaignFilter) {
                    $skipped++;
                    $skipReasons['campaign_filter']++;

                    continue;
                }

                $msgStatus = $message->status instanceof MessageStatus
                    ? $message->status
                    : MessageStatus::tryFrom((string) $message->status);

                $target = match ($msgStatus) {
                    MessageStatus::Read => CampaignRecipientStatus::Read,
                    MessageStatus::Failed => CampaignRecipientStatus::Failed,
                    MessageStatus::Delivered => CampaignRecipientStatus::Delivered,
                    default => null,
                };

                if ($target === null) {
                    $skipped++;
                    $skipReasons['no_transition']++;

                    continue;
                }

                if ($recipient->status === $target) {
                    $skipped++;
                    $skipReasons['already_synced']++;

                    continue;
                }

                if ($recipient->status && ! $recipient->status->canTransitionTo($target)) {
                    $skipped++;
                    $skipReasons['no_transition']++;

                    continue;
                }

                $previous = $recipient->status;
                $this->line("#{$recipient->id} {$previous?->value} → {$target->value} (message {$message->id})");

                if ($dryRun) {
                    $updated++;

                    continue;
                }

                $updates = [
                    'status' => $target,
                    'message_id' => $message->external_message_id
                        ? (string) $message->external_message_id
                        : $recipient->message_id,
                ];

                if ($target === CampaignRecipientStatus::Delivered) {
                    $updates['delivered_at'] = $recipient->delivered_at ?? $message->delivered_at ?? now();
                    $updates['sent_at'] = $recipient->sent_at ?? $message->sent_at ?? now();
                } elseif ($target === CampaignRecipientStatus::Read) {
                    $updates['read_at'] = $recipient->read_at ?? $message->read_at ?? now();
                    $updates['delivered_at'] = $recipient->delivered_at ?? $message->delivered_at ?? now();
                    $updates['sent_at'] = $recipient->sent_at ?? $message->sent_at ?? now();
                } elseif ($target === CampaignRecipientStatus::Failed) {
                    $updates['failed_at'] = $recipient->failed_at ?? $message->failed_at ?? now();
                    $updates['failure_reason'] = $recipient->failure_reason ?? $message->failed_reason;
                }

                $alreadyDelivered = in_array($previous, [
                    CampaignRecipientStatus::Delivered,
                    CampaignRecipientStatus::Read,
                    CampaignRecipientStatus::Response,
                ], true);
                $alreadyRead = in_array($previous, [
                    CampaignRecipientStatus::Read,
                    CampaignRecipientStatus::Response,
                ], true);

                $recipient->update($updates);
                $campaign = Campaign::query()->find($recipient->campaign_id);

                if ($campaign !== null) {
                    if (in_array($target, [CampaignRecipientStatus::Delivered, CampaignRecipientStatus::Read], true) && ! $alreadyDelivered) {
                        $campaign->increment('total_delivered');
                    }
                    if ($target === CampaignRecipientStatus::Read && ! $alreadyRead) {
                        $campaign->increment('total_read');
                    }
                    if ($target === CampaignRecipientStatus::Failed && $previous !== CampaignRecipientStatus::Failed) {
                        $campaign->increment('total_failed');
                    }
                }

                $updated++;
            }

            $this->info(($dryRun ? 'Dry-run would update' : 'Updated')." {$updated} recipient(s) via message metadata; skipped {$skipped}.");
            foreach ($skipReasons as $reason => $count) {
                if ($count > 0) {
                    $this->line("  skip:{$reason}={$count}");
                }
            }

            // Second pass: recipients still on Sent/Failed-mismatch matched by provider message_id.
            $this->syncRecipientsByProviderMessageId($campaignFilter, $limit, $dryRun);
        } finally {
            if ($endedTenancy && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return self::SUCCESS;
    }

    private function syncRecipientsByProviderMessageId(?int $campaignFilter, int $limit, bool $dryRun): void
    {
        $query = CampaignRecipient::query()
            ->whereIn('status', [
                CampaignRecipientStatus::Sent->value,
                CampaignRecipientStatus::Failed->value,
            ])
            ->whereNotNull('message_id')
            ->where('message_id', '!=', '')
            ->where('message_id', 'not like', 'local_%')
            ->orderByDesc('id')
            ->limit($limit);

        if ($campaignFilter !== null) {
            $query->where('campaign_id', $campaignFilter);
        }

        $updated = 0;
        $skipped = 0;

        foreach ($query->cursor() as $recipient) {
            $message = Message::query()
                ->where('external_message_id', (string) $recipient->message_id)
                ->whereIn('status', [
                    MessageStatus::Delivered->value,
                    MessageStatus::Read->value,
                    MessageStatus::Failed->value,
                ])
                ->first();

            if ($message === null) {
                $skipped++;

                continue;
            }

            $msgStatus = $message->status instanceof MessageStatus
                ? $message->status
                : MessageStatus::tryFrom((string) $message->status);

            $target = match ($msgStatus) {
                MessageStatus::Read => CampaignRecipientStatus::Read,
                MessageStatus::Failed => CampaignRecipientStatus::Failed,
                MessageStatus::Delivered => CampaignRecipientStatus::Delivered,
                default => null,
            };

            if ($target === null
                || $recipient->status === $target
                || ($recipient->status && ! $recipient->status->canTransitionTo($target))) {
                $skipped++;

                continue;
            }

            $previous = $recipient->status;
            $this->line("by-message-id #{$recipient->id} {$previous?->value} → {$target->value}");

            if ($dryRun) {
                $updated++;

                continue;
            }

            $updates = ['status' => $target];
            if ($target === CampaignRecipientStatus::Delivered) {
                $updates['delivered_at'] = $recipient->delivered_at ?? $message->delivered_at ?? now();
                $updates['sent_at'] = $recipient->sent_at ?? $message->sent_at ?? now();
            } elseif ($target === CampaignRecipientStatus::Read) {
                $updates['read_at'] = $recipient->read_at ?? $message->read_at ?? now();
                $updates['delivered_at'] = $recipient->delivered_at ?? $message->delivered_at ?? now();
                $updates['sent_at'] = $recipient->sent_at ?? $message->sent_at ?? now();
            } elseif ($target === CampaignRecipientStatus::Failed) {
                $updates['failed_at'] = $recipient->failed_at ?? $message->failed_at ?? now();
                $updates['failure_reason'] = $recipient->failure_reason ?? $message->failed_reason;
            }

            $alreadyDelivered = in_array($previous, [
                CampaignRecipientStatus::Delivered,
                CampaignRecipientStatus::Read,
                CampaignRecipientStatus::Response,
            ], true);
            $alreadyRead = in_array($previous, [
                CampaignRecipientStatus::Read,
                CampaignRecipientStatus::Response,
            ], true);

            $recipient->update($updates);
            $campaign = Campaign::query()->find($recipient->campaign_id);
            if ($campaign !== null) {
                if (in_array($target, [CampaignRecipientStatus::Delivered, CampaignRecipientStatus::Read], true) && ! $alreadyDelivered) {
                    $campaign->increment('total_delivered');
                }
                if ($target === CampaignRecipientStatus::Read && ! $alreadyRead) {
                    $campaign->increment('total_read');
                }
                if ($target === CampaignRecipientStatus::Failed && $previous !== CampaignRecipientStatus::Failed) {
                    $campaign->increment('total_failed');
                }
            }

            $updated++;
        }

        $this->info(($dryRun ? 'Dry-run by message_id would update' : 'Updated by message_id')." {$updated}; skipped {$skipped}.");
    }
}
