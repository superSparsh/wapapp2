<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Domains\Inbox\Support\InboxPresenter;
use App\Enums\MessageDirection;
use App\Events\Inbox\InboxMessageCreated;
use App\Events\Inbox\InboxMessageStatusUpdated;
use App\Events\Inbox\InboxThreadUpdated;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $conversation->unsetRelation('latestMessage');
        $conversation->setRelation('latestMessage', $message);

        $thread = $this->threadPayload($conversation);
        $thread['preview'] = InboxPresenter::preview($message->body) ?: ($thread['preview'] ?? '');

        $this->safeBroadcast(new InboxMessageCreated(
            tenantId: $tenantId,
            conversationUuid: $conversation->uuid,
            message: $this->messagePayload($message),
            thread: $thread,
        ));
    }

    public function messageStatusUpdated(Message $message): void
    {
        if (! $this->shouldBroadcast()) {
            return;
        }

        $tenantId = $this->tenantId();
        if ($tenantId === null) {
            return;
        }

        $message->loadMissing('conversation');
        $conversation = $message->conversation;
        if ($conversation === null) {
            return;
        }

        $this->safeBroadcast(new InboxMessageStatusUpdated(
            tenantId: $tenantId,
            conversationUuid: $conversation->uuid,
            message: $this->messagePayload($message),
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

        $this->safeBroadcast(new InboxThreadUpdated(
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
        $mediaUrl = InboxPresenter::displayMediaUrl($metadata);
        $fileName = isset($metadata['file_name']) ? (string) $metadata['file_name'] : null;

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
            'uuid' => $message->uuid,
            'body' => (string) $message->body,
            'direction' => $message->direction->value,
            'status' => $message->status->value,
            'message_type' => $message->message_type->value,
            'time' => InboxPresenter::relativeTime($message->created_at),
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
            'interactive' => isset($metadata['interactive']) && is_array($metadata['interactive']) ? $metadata['interactive'] : null,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'failed_at' => $message->failed_at?->toIso8601String(),
            'failed_reason' => $message->failed_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function threadPayload(Conversation $conversation): array
    {
        $phone = $this->settingsService->shouldMaskPhone($conversation->contact_phone);

        return [
            'uuid' => $conversation->uuid,
            'initials' => InboxPresenter::initials($conversation->contact_name, $conversation->contact_phone),
            'name' => InboxPresenter::threadTitle($conversation->contact_name, $phone),
            'phone' => $phone,
            'time' => InboxPresenter::relativeTime($conversation->last_message_at),
            'preview' => InboxPresenter::preview($conversation->latestMessage?->body),
            'unread' => (int) $conversation->unread_count,
            'assignee' => $this->assigneeLabel($conversation),
            'ai_enabled' => $conversation->response_type?->isAi() ?? false,
            'stopped' => $conversation->contact?->hasStoppedMessaging() ?? false,
        ];
    }

    private function assigneeLabel(Conversation $conversation): ?string
    {
        if ($conversation->assignedTeamMember) {
            $member = $conversation->assignedTeamMember;

            return trim($member->first_name.' '.$member->last_name) ?: $member->email;
        }

        if ($conversation->assignedUser) {
            $user = $conversation->assignedUser;

            return trim((string) ($user->first_name ?: $user->name)) ?: null;
        }

        return null;
    }

    private function safeBroadcast(ShouldBroadcastNow $event): void
    {
        try {
            // Dispatch through the event bus so Event::fake works in tests, while
            // keeping Pusher/Reverb failures inside this try/catch.
            event($event);
        } catch (BroadcastException $e) {
            Log::warning('Inbox realtime broadcast failed', [
                'event' => $event::class,
                'driver' => config('broadcasting.default'),
                'host' => config('broadcasting.connections.'.config('broadcasting.default').'.options.host'),
                'port' => config('broadcasting.connections.'.config('broadcasting.default').'.options.port'),
                'error' => $this->summarizeBroadcastError($e),
            ]);
        } catch (Throwable $e) {
            Log::warning('Inbox realtime broadcast failed', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function summarizeBroadcastError(BroadcastException $e): string
    {
        $message = $e->getMessage();

        // Pusher/Reverb often embeds a full HTML 404 page — keep logs readable.
        if (str_contains($message, '<!DOCTYPE html>') || str_contains($message, '<html')) {
            return 'Pusher/Reverb endpoint returned HTML (usually wrong REVERB_HOST/PORT or reverb not running).';
        }

        return mb_substr($message, 0, 500);
    }

    private function shouldBroadcast(): bool
    {
        if (! (bool) config('inbox.realtime_enabled', true)) {
            return false;
        }

        if (! app(MaintenanceModeService::class)->moduleEnabled('reverb_realtime')) {
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
