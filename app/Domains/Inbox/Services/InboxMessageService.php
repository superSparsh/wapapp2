<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Jobs\SendOutboundMessageJob;
use App\Domains\Inbox\Support\InboxPresenter;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
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

        $items = $chronological->map(fn (Message $message): array => $this->presentMessage($message))->all();

        return [
            'items' => $items,
            'has_more' => $hasMore,
            'oldest_id' => $chronological->first()?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentMessage(Message $message): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $messageType = $message->message_type?->value ?? 'text';
        $interactive = isset($metadata['interactive']) && is_array($metadata['interactive'])
            ? $metadata['interactive']
            : null;

        return [
            'id' => (int) $message->id,
            'uuid' => $message->uuid,
            'body' => (string) ($message->body ?? ''),
            'direction' => $message->direction->value,
            'status' => $message->status->value,
            'message_type' => $messageType,
            'time' => InboxPresenter::relativeTime($message->created_at),
            'is_outbound' => $message->direction === MessageDirection::Outbound,
            'media_url' => isset($metadata['media_url']) ? (string) $metadata['media_url'] : null,
            'file_name' => isset($metadata['file_name']) ? (string) $metadata['file_name'] : null,
            'latitude' => isset($metadata['latitude']) ? (float) $metadata['latitude'] : null,
            'longitude' => isset($metadata['longitude']) ? (float) $metadata['longitude'] : null,
            'contacts' => isset($metadata['contacts']) && is_array($metadata['contacts']) ? $metadata['contacts'] : null,
            'template_code' => isset($metadata['template_code']) ? (string) $metadata['template_code'] : null,
            'interactive' => $interactive,
            'interactive_preview' => $this->interactivePreview($interactive, (string) ($message->body ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $interactive
     * @return array{body: string, buttons: array<int, string>, type: ?string}|null
     */
    private function interactivePreview(?array $interactive, string $fallbackBody): ?array
    {
        if ($interactive === null) {
            return null;
        }

        $bodyRaw = $interactive['body'] ?? null;
        if (is_array($bodyRaw)) {
            $body = trim((string) ($bodyRaw['text'] ?? $fallbackBody));
        } elseif (is_string($bodyRaw)) {
            $body = trim($bodyRaw);
        } else {
            $body = trim($fallbackBody);
        }

        $buttons = [];

        foreach ($interactive['action']['buttons'] ?? [] as $button) {
            if (! is_array($button)) {
                continue;
            }

            $title = (string) (
                $button['reply']['title']
                ?? $button['title']
                ?? $button['text']
                ?? $button['label']
                ?? ''
            );

            if ($title !== '') {
                $buttons[] = $title;
            }
        }

        foreach ($interactive['action']['sections'] ?? [] as $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach ($section['rows'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $title = trim((string) ($row['title'] ?? ''));
                if ($title !== '') {
                    $buttons[] = $title;
                }
            }
        }

        return [
            'body' => $body !== '' ? $body : $fallbackBody,
            'buttons' => array_values(array_unique($buttons)),
            'type' => isset($interactive['type']) ? (string) $interactive['type'] : null,
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

            SendOutboundMessageJob::dispatch($message->id);

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
                app(InboxBroadcastService::class)->threadUpdated($conversation->refresh());
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

    public function markAllReadForLine(
        WhatsappLine $line,
        InboxQueryService $queryService,
        ?int $lookbackDays = null,
        ?string $scope = null,
        ?array $assigneeFilter = null,
    ): int {
        $conversationIds = $queryService->unreadConversationIds(
            line: $line,
            lookbackDays: $lookbackDays,
            scope: $scope,
            assigneeFilter: $assigneeFilter,
        );

        return $this->markAllReadByIds($conversationIds);
    }

    public function recordInbound(
        Conversation $conversation,
        string $body,
        ?string $externalMessageId = null,
        MessageType $messageType = MessageType::Text,
    ): Message {
        return DB::transaction(function () use ($conversation, $body, $externalMessageId, $messageType): Message {
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
                'delivered_at' => $now,
            ]);

            $conversation->forceFill([
                'last_message_at' => $now,
                'unread_count' => $conversation->unread_count + 1,
            ])->save();

            app(InboxBroadcastService::class)->messageCreated($conversation->refresh(), $message);

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
