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
            'contact:id,phone,status,opt_in_status,metadata',
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
            'contact:id,phone,status,opt_in_status,metadata',
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
        $metadata = is_array($message->metadata) ? $message->metadata : [];

        return [
            'uuid' => $message->uuid,
            'body' => (string) $message->body,
            'direction' => $message->direction->value,
            'status' => $message->status->value,
            'message_type' => $message->message_type->value,
            'time' => InboxPresenter::relativeTime($message->created_at),
            'is_outbound' => $message->direction === MessageDirection::Outbound,
            'media_url' => isset($metadata['media_url']) ? (string) $metadata['media_url'] : null,
            'file_name' => isset($metadata['file_name']) ? (string) $metadata['file_name'] : null,
            'latitude' => isset($metadata['latitude']) ? (float) $metadata['latitude'] : null,
            'longitude' => isset($metadata['longitude']) ? (float) $metadata['longitude'] : null,
            'contacts' => isset($metadata['contacts']) && is_array($metadata['contacts']) ? $metadata['contacts'] : null,
            'template_code' => isset($metadata['template_code']) ? (string) $metadata['template_code'] : null,
            'interactive' => isset($metadata['interactive']) && is_array($metadata['interactive']) ? $metadata['interactive'] : null,
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
            'stopped' => $conversation->contact?->hasStoppedMessaging() ?? false,
        ];
    }

    private function shouldBroadcast(): bool
    {
        if (! (bool) config('inbox.realtime_enabled', true)) {
            return false;
        }

        if (! app(\App\Domains\Admin\Services\MaintenanceModeService::class)->moduleEnabled('reverb_realtime')) {
            return false;
        }

        $connection = (string) config('broadcasting.default', 'null');

        // Only websocket-capable drivers deliver live inbox events to Echo.
        return in_array($connection, ['reverb', 'pusher', 'ably'], true);
    }

    private function tenantId(): ?string
    {
        $tenantId = tenant('id');

        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }
}
