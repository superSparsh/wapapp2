<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Jobs\SendOutboundMessageJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\InboxPresenter;
use Illuminate\Support\Facades\DB;

class InboxMessageService
{
    /**
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     has_more: bool,
     *     oldest_id: ?int
     * }
     */
    public function paginateMessages(
        Conversation $conversation,
        ?int $beforeId = null,
        ?int $lookbackDays = null,
        ?int $limit = null,
    ): array {
        $limit = max(1, min(
            (int) config('inbox.max_messages_per_load', 400),
            $limit ?? (int) config('inbox.messages_per_page', 50),
        ));

        $lookbackDays = $this->normalizeLookbackDays($lookbackDays);
        $since = now()->subDays($lookbackDays);

        $query = Message::query()
            ->select(['id', 'uuid', 'body', 'direction', 'status', 'message_type', 'metadata', 'created_at'])
            ->where('conversation_id', $conversation->id)
            ->where('created_at', '>=', $since)
            ->when($beforeId !== null, fn ($builder) => $builder->where('id', '<', $beforeId))
            ->orderByDesc('id');

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $chronological = $rows->reverse()->values();

        $items = $chronological->map(fn (Message $message): array => [
            'uuid' => $message->uuid,
            'body' => (string) $message->body,
            'direction' => $message->direction->value,
            'status' => $message->status->value,
            'message_type' => $message->message_type->value,
            'time' => InboxPresenter::relativeTime($message->created_at),
            'is_outbound' => $message->direction === MessageDirection::Outbound,
            'metadata' => $message->metadata,
        ])->all();

        return [
            'items' => $items,
            'has_more' => $hasMore,
            'oldest_id' => $chronological->first()?->id,
        ];
    }

    public function sendText(Conversation $conversation, string $body): Message
    {
        $body = trim($body);

        abort_if($body === '', 422, 'Message body is required.');

        return DB::transaction(function () use ($conversation, $body): Message {
            $now = now();

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'body' => $body,
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::Text,
                'status' => MessageStatus::Queued,
            ]);

            $conversation->forceFill([
                'last_message_at' => $now,
                'replied_at' => $now,
            ])->save();

            SendOutboundMessageJob::dispatch($message->id)
                ->onQueue((string) config('whatsapp.outbound_queue', 'default'));

            return $message->refresh();
        });
    }

    public function markRead(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('direction', MessageDirection::Inbound)
                ->whereNot('status', MessageStatus::Read)
                ->update([
                    'status' => MessageStatus::Read,
                    'read_at' => now(),
                ]);

            if ($conversation->unread_count > 0) {
                $conversation->forceFill(['unread_count' => 0])->save();
            }
        });
    }

    /**
     * @param  array<int, int>  $conversationIds
     */
    public function markAllReadByIds(array $conversationIds): int
    {
        if ($conversationIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($conversationIds): int {
            $now = now();

            Message::query()
                ->whereIn('conversation_id', $conversationIds)
                ->where('direction', MessageDirection::Inbound)
                ->whereNot('status', MessageStatus::Read)
                ->update([
                    'status' => MessageStatus::Read,
                    'read_at' => $now,
                ]);

            return Conversation::query()
                ->whereIn('id', $conversationIds)
                ->where('unread_count', '>', 0)
                ->update(['unread_count' => 0]);
        });
    }

    public function recordInbound(
        Conversation $conversation,
        string $body,
        ?string $externalMessageId = null,
        MessageType $messageType = MessageType::Text,
        ?array $metadata = null,
    ): Message {
        return DB::transaction(function () use ($conversation, $body, $externalMessageId, $messageType, $metadata): Message {
            if ($externalMessageId !== null && $externalMessageId !== '') {
                $existing = Message::query()
                    ->where('external_message_id', $externalMessageId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $now = now();

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'body' => $body,
                'direction' => MessageDirection::Inbound,
                'message_type' => $messageType,
                'status' => MessageStatus::Delivered,
                'external_message_id' => $externalMessageId,
                'metadata' => $metadata,
                'delivered_at' => $now,
            ]);

            $conversation->forceFill([
                'last_message_at' => $now,
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            return $message;
        });
    }

    private function normalizeLookbackDays(?int $lookbackDays): int
    {
        $lookbackDays ??= (int) config('inbox.default_lookback_days', 7);
        $allowed = config('inbox.allowed_lookback_days', [1, 3, 7, 30, 90]);

        if (! in_array($lookbackDays, $allowed, true)) {
            return (int) config('inbox.default_lookback_days', 7);
        }

        return $lookbackDays;
    }
}
