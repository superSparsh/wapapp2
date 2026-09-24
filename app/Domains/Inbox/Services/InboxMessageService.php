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
use Illuminate\Support\Facades\Log;

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
            ->select([
                'id',
                'uuid',
                'body',
                'direction',
                'status',
                'message_type',
                'metadata',
                'created_at',
                'sent_at',
                'delivered_at',
                'read_at',
                'failed_at',
                'failed_reason',
            ])
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

        $mediaUrl = InboxPresenter::displayMediaUrl($metadata);
        $fileName = isset($metadata['file_name']) ? (string) $metadata['file_name'] : null;

        // Backfill from raw webhook payload when older inbound media lacked media_url.
        if (($mediaUrl === null || $mediaUrl === '') && is_array($metadata['raw_message'] ?? null)) {
            $raw = $metadata['raw_message'];
            foreach (['link', 'url', 'media_url'] as $key) {
                if (isset($raw[$key]) && is_string($raw[$key]) && trim($raw[$key]) !== '') {
                    $mediaUrl = trim($raw[$key]);
                    break;
                }
            }
            if ($fileName === null || $fileName === '') {
                foreach (['fileName', 'filename', 'file_name'] as $key) {
                    if (isset($raw[$key]) && is_string($raw[$key]) && trim($raw[$key]) !== '') {
                        $fileName = trim($raw[$key]);
                        break;
                    }
                }
            }
        }

        return [
            'id' => (int) $message->id,
            'uuid' => $message->uuid,
            'body' => (string) ($message->body ?? ''),
            'direction' => $message->direction->value,
            'status' => $message->status->value,
            'message_type' => $messageType,
            'time' => InboxPresenter::clockTime($message->created_at),
            'date_key' => InboxPresenter::dateKey($message->created_at),
            'date_label' => InboxPresenter::dateLabel($message->created_at),
            'created_at' => $message->created_at?->toIso8601String(),
            'is_outbound' => $message->direction === MessageDirection::Outbound,
            'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
            'file_name' => $fileName !== '' ? $fileName : null,
            'latitude' => isset($metadata['latitude']) ? (float) $metadata['latitude'] : null,
            'longitude' => isset($metadata['longitude']) ? (float) $metadata['longitude'] : null,
            'contacts' => isset($metadata['contacts']) && is_array($metadata['contacts']) ? $metadata['contacts'] : null,
            'template_code' => isset($metadata['template_code']) ? (string) $metadata['template_code'] : null,
            'template_name' => isset($metadata['template_name']) ? (string) $metadata['template_name'] : null,
            'template_buttons' => isset($metadata['template_buttons']) && is_array($metadata['template_buttons'])
                ? $metadata['template_buttons']
                : [],
            'interactive' => $interactive,
            'interactive_preview' => $this->interactivePreview($interactive, (string) ($message->body ?? '')),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'failed_reason' => $message->failed_reason,
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

        $message = DB::transaction(function () use ($conversation, $body): Message {
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

            return $message->refresh();
        });

        SendOutboundMessageJob::dispatch($message->id);

        return $message;
    }

    public function markRead(Conversation $conversation): void
    {
        $shouldBroadcast = false;

        DB::transaction(function () use ($conversation, &$shouldBroadcast): void {
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
                $shouldBroadcast = true;
            }
        });

        // Broadcast after the tenant transaction commits. shouldBroadcast() may call
        // tenancy()->central(), which would otherwise discard the unread clear.
        if ($shouldBroadcast) {
            app(InboxBroadcastService::class)->threadUpdated($conversation->refresh());
        }
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
        $message = DB::transaction(function () use ($conversation, $body, $externalMessageId, $messageType): Message {
            if ($externalMessageId !== null && $externalMessageId !== '') {
                $existing = Message::query()
                    ->where('external_message_id', $externalMessageId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $now = now();

            $created = Message::query()->create([
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

            return $created;
        });

        try {
            app(InboxBroadcastService::class)->messageCreated($conversation->refresh(), $message);
        } catch (\Throwable $e) {
            Log::warning('Inbox inbound broadcast failed', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $message;
    }

    private function normalizeLookbackDays(?int $lookbackDays): int
    {
        $lookbackDays ??= (int) config('inbox.default_lookback_days', 7);
        $allowed = config('inbox.allowed_lookback_days', [1, 3, 7, 90, 180, 365]);

        if (! in_array($lookbackDays, $allowed, true)) {
            return (int) config('inbox.default_lookback_days', 7);
        }

        return $lookbackDays;
    }
}
