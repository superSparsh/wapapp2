<?php

declare(strict_types=1);

namespace App\Domains\Audience\Console\Commands;

use App\Domains\Audience\Services\OptInMessageService;
use App\Enums\MessageStatus;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Tenant;
use App\Support\PhoneNormalizer;
use Illuminate\Console\Command;

/**
 * Backfill contacts stuck on opt-in "pending" when inbox messages already delivered/failed.
 */
class SyncOptInDeliveryFromMessagesCommand extends Command
{
    protected $signature = 'audience:sync-opt-in-delivery
                            {--tenant= : Tenant id}
                            {--limit=5000 : Max opt-in messages to scan}
                            {--dry-run : Show actions without writing}';

    protected $description = 'Sync contact opt-in delivery status from delivered/failed inbox messages';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));
        $tenantId = trim((string) $this->option('tenant'));
        $ended = false;

        if (! tenancy()->initialized) {
            if ($tenantId === '') {
                $this->error('Pass --tenant=');

                return self::FAILURE;
            }
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                $this->error("Tenant not found: {$tenantId}");

                return self::FAILURE;
            }
            tenancy()->initialize($tenant);
            $ended = true;
        }

        try {
            $updated = 0;
            $skipped = 0;

            $messages = Message::query()
                ->where('direction', 'outbound')
                ->whereIn('status', [
                    MessageStatus::Delivered->value,
                    MessageStatus::Read->value,
                    MessageStatus::Failed->value,
                ])
                ->where(function ($q): void {
                    $q->where('metadata->wallet_source', 'opt_in')
                        ->orWhereNotNull('metadata->opt_in_contact_id');
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get();

            foreach ($messages as $message) {
                $meta = is_array($message->metadata) ? $message->metadata : [];
                $contactId = (int) ($meta['opt_in_contact_id'] ?? 0);
                $contact = $contactId > 0 ? Contact::query()->find($contactId) : null;

                if ($contact === null) {
                    $message->loadMissing('conversation');
                    $raw = (string) ($meta['contact_phone'] ?? $message->conversation?->contact_phone ?? '');
                    $variants = PhoneNormalizer::lookupVariants($raw);
                    if ($variants !== []) {
                        $contact = Contact::query()
                            ->whereIn('phone', $variants)
                            ->where(function ($q): void {
                                $q->where('send_opt_in_message', 'yes')
                                    ->orWhere('opt_in_message_sent', true);
                            })
                            ->orderByDesc('id')
                            ->first();
                    }
                }

                if ($contact === null) {
                    $skipped++;

                    continue;
                }

                $msgStatus = $message->status instanceof MessageStatus
                    ? $message->status
                    : MessageStatus::tryFrom((string) $message->status);

                $target = match ($msgStatus) {
                    MessageStatus::Failed => OptInMessageService::DELIVERY_FAILED,
                    MessageStatus::Delivered, MessageStatus::Read => OptInMessageService::DELIVERY_DELIVERED,
                    default => null,
                };

                if ($target === null || $contact->opt_in_message_delivery_status === $target) {
                    $skipped++;

                    continue;
                }

                // Don't downgrade delivered → failed from an older message.
                if ($contact->opt_in_message_delivery_status === OptInMessageService::DELIVERY_DELIVERED
                    && $target === OptInMessageService::DELIVERY_FAILED) {
                    $skipped++;

                    continue;
                }

                $this->line("contact #{$contact->id} {$contact->phone}: {$contact->opt_in_message_delivery_status} → {$target}");

                if ($dryRun) {
                    $updated++;

                    continue;
                }

                $updates = [
                    'opt_in_message_delivery_status' => $target,
                    'opt_in_message_sent' => true,
                    'opt_in_message_sent_at' => $contact->opt_in_message_sent_at ?? $message->sent_at ?? now(),
                ];

                if ($target === OptInMessageService::DELIVERY_DELIVERED) {
                    $updates['opt_in_message_delivered_at'] = $contact->opt_in_message_delivered_at
                        ?? $message->delivered_at
                        ?? $message->read_at
                        ?? now();
                    $updates['opt_in_message_delivery_error'] = null;
                } else {
                    $updates['opt_in_message_delivery_error'] = $message->failed_reason ?: 'Delivery failed';
                }

                $contact->forceFill($updates)->save();
                $updated++;
            }

            $this->info(($dryRun ? 'Dry-run would update' : 'Updated')." {$updated}; skipped {$skipped}.");
        } finally {
            if ($ended && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return self::SUCCESS;
    }
}
