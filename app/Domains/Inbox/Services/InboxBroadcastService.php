<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Support\InboxPresenter;
use App\Enums\MessageDirection;
use App\Events\Inbox\InboxMessageCreated;
use App\Events\Inbox\InboxThreadUpdated;
use App\Models\Conversation;
use App\Models\Message;

class InboxBroadcastService
{
    public function __construct(
        private readonly InboxSettingsService $settingsService,
    ) {}

    public function messageCreated(Conversation $conversation, Message $message): void
    {
        if (! $this->shouldBroadcast()) {
            return;
        }

        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            return;
        }

        $conversation->loadMissing([
            'latestMessage' => fn ($query) => $query->select(
                'messages.id',
                'messages.conversation_id',
                'messages.body',
                'messages.created_at',
            ),
            'assignedUser:id,uuid,name,first_name',
            'assignedTeamMember:id,uuid,first_name,last_name,email',
        ]);

        $thread = $this->threadPayload($conversation);

        broadcast(new InboxMessageCreated(
            tenantId: $tenantId,
            conversationUuid: $conversation->uuid,
            message: $this->messagePayload($message),
            thread: $thread,
        ));
    }

    public function threadUpdated(Conversation $conversation): void
    {
        if (! $this->shouldBroadcast()) {
            return;
        }

        $tenantId = $this->tenantId();

        if ($tenantId === null) {
            return;
        }

        $conversation->loadMissing([
            'latestMessage' => fn ($query) => $query->select(
                'messages.id',
                'messages.conversation_id',
                'messages.body',
                'messages.created_at',
            ),
            'assignedUser:id,uuid,name,first_name',
            'assignedTeamMember:id,uuid,first_name,last_name,email',
        ]);

        broadcast(new InboxThreadUpdated(
            tenantId: $tenantId,
            conversationUuid: $conversation->uuid,
            thread: $this->threadPayload($conversation),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(Message $message): array
    {
        return [
            'uuid' => $message->uuid,
            'body' => (string) $message->body,
            'direction' => $message->direction->value,
            'status' => $message->status->value,
            'message_type' => $message->message_type->value,
            'time' => InboxPresenter::relativeTime($message->created_at),
            'is_outbound' => $message->direction === MessageDirection::Outbound,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function threadPayload(Conversation $conversation): array
    {
        $assigneeKey = null;

        if ($conversation->assignedUser) {
            $assigneeKey = 'user:'.$conversation->assignedUser->uuid;
        } elseif ($conversation->assignedTeamMember) {
            $assigneeKey = 'member:'.$conversation->assignedTeamMember->uuid;
        }

        return [
            'uuid' => $conversation->uuid,
            'initials' => InboxPresenter::initials($conversation->contact_name, $conversation->contact_phone),
            'name' => $conversation->contact_name ?: $this->settingsService->shouldMaskPhone($conversation->contact_phone),
            'phone' => $this->settingsService->shouldMaskPhone($conversation->contact_phone),
            'time' => InboxPresenter::relativeTime($conversation->last_message_at),
            'preview' => InboxPresenter::preview($conversation->latestMessage?->body),
            'unread' => (int) $conversation->unread_count,
            'assignee' => $assigneeKey,
            'ai_enabled' => $conversation->response_type?->isAi() ?? false,
        ];
    }

    private function shouldBroadcast(): bool
    {
        if (! (bool) config('inbox.realtime_enabled', true)) {
            return false;
        }

        $connection = (string) config('broadcasting.default', 'null');

        return $connection !== 'null' && $connection !== '';
    }

    private function tenantId(): ?string
    {
        $tenantId = tenant('id');

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }
}
