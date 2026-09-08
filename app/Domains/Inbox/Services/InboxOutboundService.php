<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Inbox\Jobs\SendOutboundMessageJob;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboxOutboundService
{
    public function __construct(
        private readonly MessagingWindowService $windowService,
        private readonly InboxMediaService $mediaService,
        private readonly AlibabaCamsClient $camsClient,
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

        $stored = $this->mediaService->store($file);
        $messageType = $this->resolveMediaMessageType($mediaType);

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $caption,
            messageType: $messageType,
            metadata: [
                'media_url' => $stored['url'],
                'media_path' => $stored['path'],
                'file_name' => $stored['original_name'],
                'file_type' => $stored['mime'],
            ],
        );
    }

    /**
     * Send media from a remote URL (drip / chatbot media nodes).
     */
    public function sendMediaFromUrl(
        Conversation $conversation,
        string $mediaUrl,
        string $mediaType = 'image',
        ?string $caption = null,
        ?string $fileName = null,
        bool $enforceWindow = false,
    ): Message {
        if ($enforceWindow) {
            $this->windowService->assertWithinServiceWindow($conversation);
        }

        $mediaUrl = trim($mediaUrl);
        abort_if($mediaUrl === '', 422, 'Media URL is required.');

        $messageType = $this->resolveMediaMessageType($mediaType);

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $caption,
            messageType: $messageType,
            metadata: [
                'media_url' => $mediaUrl,
                'file_name' => $fileName ?? basename(parse_url($mediaUrl, PHP_URL_PATH) ?: 'media'),
            ],
        );
    }

    public function sendSticker(Conversation $conversation, UploadedFile $file): Message
    {
        $this->windowService->assertWithinServiceWindow($conversation);

        $stored = $this->mediaService->store($file);

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: null,
            messageType: MessageType::Sticker,
            metadata: [
                'media_url' => $stored['url'],
                'media_path' => $stored['path'],
                'file_name' => $stored['original_name'],
                'file_type' => $stored['mime'],
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
     * @param  array<string, mixed>  $templateParams
     */
    public function sendTemplate(
        Conversation $conversation,
        string $templateCode,
        array $templateParams = [],
        ?string $language = null,
        bool $sendImmediately = false,
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
            sendImmediately: $sendImmediately,
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
     * Send real-time typing indicator to WhatsApp user via Alibaba CAMS.
     */
    public function sendTypingIndicator(Conversation $conversation): bool
    {
        $conversation->loadMissing('whatsappLine');
        $line = $conversation->whatsappLine;

        if ($line === null || ! $this->camsClient->isConfigured() || blank($line->alibaba_cust_space_id)) {
            return false;
        }

        $to = PhoneNormalizer::normalize($conversation->contact_phone) ?? preg_replace('/\D+/', '', (string) $conversation->contact_phone) ?? '';
        $from = PhoneNormalizer::normalize($line->phone) ?? preg_replace('/\D+/', '', (string) $line->phone) ?? '';

        $params = [
            'From' => $from,
            'To' => $to,
            'Type' => 'message',
            'MessageType' => 'typing_indicator',
            'CustSpaceId' => $line->alibaba_cust_space_id,
        ];

        try {
            $response = $this->camsClient->sendChatappMessage($params);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Failed to send WhatsApp typing indicator via CAMS: '.$e->getMessage(), [
                'conversation_id' => $conversation->id,
            ]);

            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(WhatsappLine $line): array
    {
        if (! $this->camsClient->isConfigured() || blank($line->alibaba_cust_space_id)) {
            return [];
        }

        $response = $this->camsClient->listTemplates([
            'CustSpaceId' => $line->alibaba_cust_space_id,
        ]);

        if (! $response->successful()) {
            return [];
        }

        $templates = Arr::get($response->json(), 'ListTemplate', Arr::get($response->json(), 'Data.ListTemplate', []));

        if (! is_array($templates)) {
            return [];
        }

        return collect($templates)
            ->filter(fn ($template) => is_array($template))
            ->map(fn (array $template): array => [
                'code' => (string) ($template['TemplateCode'] ?? $template['templateCode'] ?? ''),
                'name' => (string) ($template['TemplateName'] ?? $template['templateName'] ?? $template['TemplateCode'] ?? ''),
                'language' => (string) ($template['Language'] ?? $template['language'] ?? config('whatsapp.alibaba.default_language', 'en_GB')),
                'category' => (string) ($template['Category'] ?? $template['category'] ?? ''),
            ])
            ->filter(fn (array $template) => $template['code'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function createOutboundMessage(
        Conversation $conversation,
        ?string $body,
        MessageType $messageType,
        ?array $metadata = null,
        bool $sendImmediately = false,
    ): Message {
        if ($messageType === MessageType::Text) {
            abort_if(blank($body), 422, 'Message body is required.');
        }

        $message = DB::transaction(function () use ($conversation, $body, $messageType, $metadata): Message {
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

            return $message->refresh();
        });

        app(InboxBroadcastService::class)->messageCreated($conversation->refresh(), $message);

        if ($sendImmediately) {
            SendOutboundMessageJob::dispatchSync($message->id);

            return $message->refresh();
        }

        SendOutboundMessageJob::dispatch($message->id)
            ->onQueue((string) config('whatsapp.outbound_queue', 'default'));

        return $message->refresh();
    }

    private function resolveMediaMessageType(string $mediaType): MessageType
    {
        return match (strtolower($mediaType)) {
            'video' => MessageType::Video,
            'audio' => MessageType::Audio,
            'document' => MessageType::Document,
            default => MessageType::Image,
        };
    }
}
