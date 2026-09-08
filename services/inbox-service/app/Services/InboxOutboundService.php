<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Jobs\SendOutboundMessageJob;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InboxOutboundService
{
    public function __construct(
        private readonly MessagingWindowService $windowService,
    ) {}

    public function sendText(Conversation $conversation, string $body, bool $enforceWindow = true): Message
    {
        if ($enforceWindow) {
            $this->windowService->assertWithinServiceWindow($conversation);
        }

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: trim($body),
            messageType: MessageType::Text,
        );
    }

    public function sendMedia(
        Conversation $conversation,
        UploadedFile $file,
        string $mediaType,
        ?string $caption = null,
    ): Message {
        $this->windowService->assertWithinServiceWindow($conversation);

        $disk = (string) config('whatsapp.media.disk', 'public');
        $directory = (string) config('whatsapp.media.directory', 'inbox/outbound');
        $path = $file->store($directory, $disk);

        $messageType = match (strtolower($mediaType)) {
            'video' => MessageType::Video,
            'audio' => MessageType::Audio,
            'document' => MessageType::Document,
            default => MessageType::Image,
        };

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $caption,
            messageType: $messageType,
            metadata: [
                'media_url' => Storage::disk($disk)->url($path),
                'media_path' => $path,
                'file_name' => (string) $file->getClientOriginalName(),
                'file_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            ],
        );
    }

    public function sendMediaFromUrl(
        Conversation $conversation,
        string $mediaUrl,
        string $mediaType,
        ?string $caption = null,
        ?string $fileName = null,
        ?string $fileType = null,
    ): Message {
        $this->windowService->assertWithinServiceWindow($conversation);

        $messageType = match (strtolower($mediaType)) {
            'video' => MessageType::Video,
            'audio' => MessageType::Audio,
            'document' => MessageType::Document,
            default => MessageType::Image,
        };

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $caption,
            messageType: $messageType,
            metadata: [
                'media_url' => $mediaUrl,
                'file_name' => $fileName ?? 'file',
                'file_type' => $fileType ?? 'application/octet-stream',
            ],
        );
    }

    public function sendSticker(Conversation $conversation, UploadedFile $file): Message
    {
        $this->windowService->assertWithinServiceWindow($conversation);

        $disk = (string) config('whatsapp.media.disk', 'public');
        $directory = (string) config('whatsapp.media.directory', 'inbox/outbound');
        $path = $file->store($directory, $disk);

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: null,
            messageType: MessageType::Sticker,
            metadata: [
                'media_url' => Storage::disk($disk)->url($path),
                'media_path' => $path,
                'file_name' => (string) $file->getClientOriginalName(),
                'file_type' => (string) ($file->getMimeType() ?? 'application/octet-stream'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $templateParams
     */
    public function sendTemplate(
        Conversation $conversation,
        string $templateCode,
        array $templateParams = [],
        ?string $language = null,
    ): Message {
        $templateCode = trim($templateCode);
        abort_if($templateCode === '', 422, 'Template code is required.');

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $templateCode,
            messageType: MessageType::Template,
            metadata: [
                'template_code' => $templateCode,
                'template_params' => $templateParams,
                'language' => $language ?? config('whatsapp.alibaba.default_language', 'en_GB'),
            ],
        );
    }

    public function sendLocation(Conversation $conversation, float $latitude, float $longitude): Message
    {
        $this->windowService->assertWithinServiceWindow($conversation);

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: sprintf('%s, %s', $latitude, $longitude),
            messageType: MessageType::Location,
            metadata: [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $interactiveContent
     */
    public function sendInteractive(
        Conversation $conversation,
        array $interactiveContent,
        ?string $previewBody = null,
        bool $enforceWindow = false,
    ): Message {
        if ($enforceWindow) {
            $this->windowService->assertWithinServiceWindow($conversation);
        }

        $body = $previewBody ?? (string) ($interactiveContent['body']['text'] ?? '[Interactive message]');

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $body,
            messageType: MessageType::Interactive,
            metadata: [
                'interactive' => $interactiveContent,
                'interactive_type' => (string) ($interactiveContent['type'] ?? 'interactive'),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function createOutboundMessage(
        Conversation $conversation,
        ?string $body,
        MessageType $messageType,
        ?array $metadata = null,
    ): Message {
        if ($messageType === MessageType::Text) {
            abort_if(blank($body), 422, 'Message body is required.');
        }

        return DB::transaction(function () use ($conversation, $body, $messageType, $metadata): Message {
            $now = now();

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'body' => $body,
                'direction' => MessageDirection::Outbound,
                'message_type' => $messageType,
                'status' => MessageStatus::Queued,
                'metadata' => $metadata,
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
}
