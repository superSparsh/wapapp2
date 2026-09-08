<?php

declare(strict_types=1);

namespace App\Domains\LegacyMigration\Importers;

use App\Domains\LegacyMigration\DTO\LegacyCustomerSnapshot;
use App\Domains\LegacyMigration\Support\LegacyConnection;
use App\Domains\LegacyMigration\Support\MigrationIdMap;
use App\Domains\LegacyMigration\Support\MigrationReport;
use App\Enums\ConversationResponseType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;

final class InboxImporter implements LegacyImporter
{
    public function __construct(
        private readonly LegacyConnection $legacy,
    ) {}

    public function key(): string
    {
        return 'inbox';
    }

    public function import(
        LegacyCustomerSnapshot $customer,
        Tenant $tenant,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun = false,
    ): void {
        if (! $this->legacy->tableExists('sub_replies') || ! $this->legacy->tableExists('conversations')) {
            return;
        }

        $lines = $this->legacy->db()->table('new_contacts')
            ->where('customer_id', $customer->id)
            ->get(['id', 'phone']);

        if ($lines->isEmpty()) {
            return;
        }

        $phoneToLegacyLine = [];
        foreach ($lines as $line) {
            $normalized = PhoneNormalizer::normalize((string) $line->phone);
            if ($normalized !== null) {
                $phoneToLegacyLine[$normalized] = (int) $line->id;
                $phoneToLegacyLine[(string) $line->phone] = (int) $line->id;
            }
        }

        $phones = $lines->pluck('phone')->filter()->values()->all();
        $chunk = (int) config('legacy-migration.chunks.inbox_threads', 200);

        $this->legacy->db()->table('sub_replies')
            ->whereIn('msg_to', $phones)
            ->orderBy('id')
            ->chunkById($chunk, function ($threads) use ($ids, $report, $dryRun, $phoneToLegacyLine): void {
                foreach ($threads as $thread) {
                    $this->importThread($thread, $ids, $report, $dryRun, $phoneToLegacyLine);
                }
            });
    }

    /**
     * @param  array<string, int>  $phoneToLegacyLine
     */
    private function importThread(
        object $thread,
        MigrationIdMap $ids,
        MigrationReport $report,
        bool $dryRun,
        array $phoneToLegacyLine,
    ): void {
        $linePhone = PhoneNormalizer::normalize((string) $thread->msg_to);
        $contactPhone = PhoneNormalizer::normalize((string) $thread->msg_from);

        if ($linePhone === null || $contactPhone === null) {
            $report->bump($this->key(), 'skipped');

            return;
        }

        $legacyLineId = $phoneToLegacyLine[$linePhone] ?? $phoneToLegacyLine[(string) $thread->msg_to] ?? null;
        $lineId = $legacyLineId ? $ids->getInt('line', $legacyLineId) : null;
        $lineId ??= WhatsappLine::query()->where('phone', $linePhone)->value('id');

        if ($lineId === null) {
            $report->bump($this->key(), 'skipped');

            return;
        }

        if ($dryRun) {
            $exists = Conversation::query()
                ->where('whatsapp_line_id', $lineId)
                ->where('contact_phone', $contactPhone)
                ->exists();
            $report->bump($this->key(), $exists ? 'updated' : 'created');

            return;
        }

        $contactId = $ids->get('contact_phone', $contactPhone);
        $contactId = $contactId !== null ? (int) $contactId : Contact::query()->where('phone', $contactPhone)->value('id');

        $conversation = Conversation::query()->updateOrCreate(
            [
                'whatsapp_line_id' => $lineId,
                'contact_phone' => $contactPhone,
            ],
            [
                'contact_id' => $contactId,
                'line_phone' => $linePhone,
                'contact_name' => filled($thread->sender_name ?? null) ? (string) $thread->sender_name : null,
                'status' => ConversationStatus::Open,
                'response_type' => $this->mapResponseType($thread->response_type ?? null),
                'lead_score' => (int) ($thread->lead_score ?? 0),
                'qualification_status' => filled($thread->qualification_status ?? null)
                    ? (string) $thread->qualification_status
                    : 'pending',
                'last_message_at' => $this->sanitizeDateTime($thread->messaged_at ?? $thread->updated_at ?? null),
                'replied_at' => $this->sanitizeDateTime($thread->replied_at ?? null),
            ],
        );

        $ids->put('inbox_thread', (int) $thread->id, $conversation->id);
        $report->bump($this->key(), $conversation->wasRecentlyCreated ? 'created' : 'updated');

        $this->importMessages((int) $thread->id, (int) $conversation->id, $report);
    }

    private function importMessages(int $legacyThreadId, int $conversationId, MigrationReport $report): void
    {
        $messageChunk = (int) config('legacy-migration.chunks.messages', 300);

        $this->legacy->db()->table('conversations')
            ->where('sub_reply_id', $legacyThreadId)
            ->orderBy('id')
            ->chunkById($messageChunk, function ($rows) use ($conversationId, $report): void {
                foreach ($rows as $row) {
                    $externalId = filled($row->msg_id ?? null)
                        ? (string) $row->msg_id
                        : 'legacy-'.$row->id;

                    $existing = Message::query()->where('external_message_id', $externalId)->first();
                    if ($existing !== null) {
                        $report->bump('inbox_messages', 'skipped');

                        continue;
                    }

                    $direction = $this->mapDirection($row->type ?? $row->new_type ?? null);
                    $createdAt = $this->sanitizeDateTime($row->created_at ?? null) ?? now();

                    Message::query()->create([
                        'conversation_id' => $conversationId,
                        'external_message_id' => $externalId,
                        'body' => $row->msg ?? $row->reply_msg ?? null,
                        'direction' => $direction,
                        'message_type' => MessageType::Text,
                        'status' => $this->mapMessageStatus($row),
                        'failed_reason' => $row->failed_reason ?? null,
                        'sent_at' => $this->sanitizeDateTime($row->sent_at ?? $row->created_at ?? null),
                        'delivered_at' => $this->sanitizeDateTime($row->delivered_at ?? null),
                        'read_at' => $this->sanitizeDateTime($row->read_at ?? null),
                        'failed_at' => $this->sanitizeDateTime($row->failed_at ?? null),
                        'metadata' => [
                            'legacy_conversation_id' => (int) $row->id,
                            'legacy_sub_reply_id' => (int) ($row->sub_reply_id ?? 0),
                        ],
                        'created_at' => $createdAt,
                        'updated_at' => $this->sanitizeDateTime($row->updated_at ?? null) ?? $createdAt,
                    ]);

                    $report->bump('inbox_messages', 'created');
                }
            });
    }

    private function sanitizeDateTime(mixed $value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
            return null;
        }

        try {
            $date = \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        // Legacy often stores unix epoch / zero timestamps.
        if ($date->year < 1980) {
            return null;
        }

        return $date;
    }

    private function mapDirection(mixed $type): MessageDirection
    {
        $value = strtolower((string) $type);

        return match (true) {
            in_array($value, ['sent', 'outbound', 'out', '1'], true) => MessageDirection::Outbound,
            default => MessageDirection::Inbound,
        };
    }

    private function mapResponseType(mixed $raw): ConversationResponseType
    {
        $value = strtolower((string) ($raw ?? ''));

        return match (true) {
            str_contains($value, 'ai') => ConversationResponseType::Ai,
            default => ConversationResponseType::Human,
        };
    }

    private function mapMessageStatus(object $row): MessageStatus
    {
        if ($this->sanitizeDateTime($row->failed_at ?? null) !== null) {
            return MessageStatus::Failed;
        }
        if ($this->sanitizeDateTime($row->read_at ?? null) !== null) {
            return MessageStatus::Read;
        }
        if ($this->sanitizeDateTime($row->delivered_at ?? null) !== null) {
            return MessageStatus::Delivered;
        }
        if ($this->sanitizeDateTime($row->sent_at ?? null) !== null) {
            return MessageStatus::Sent;
        }

        return MessageStatus::Pending;
    }
}
