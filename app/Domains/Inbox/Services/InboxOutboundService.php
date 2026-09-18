<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Billing\Services\WalletService;
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
        private readonly WalletService $walletService,
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
     * @param  array{
     *     name: string,
     *     phone: string,
     *     first_name?: string|null,
     *     last_name?: string|null,
     *     phone_type?: string|null,
     *     email?: string|null,
     *     company?: string|null
     * }  $contact
     */
    public function sendContact(Conversation $conversation, array $contact): Message
    {
        $this->windowService->assertWithinServiceWindow($conversation);

        $formattedName = trim((string) ($contact['name'] ?? ''));
        abort_if($formattedName === '', 422, 'Contact name is required.');

        $phone = trim((string) ($contact['phone'] ?? ''));
        abort_if($phone === '', 422, 'Contact phone is required.');

        $phoneType = strtoupper(trim((string) ($contact['phone_type'] ?? 'CELL')));
        if (! in_array($phoneType, ['CELL', 'WORK', 'HOME', 'MAIN', 'IPHONE'], true)) {
            $phoneType = 'CELL';
        }

        $waId = PhoneNormalizer::normalize($phone) ?? (preg_replace('/\D+/', '', $phone) ?: null);

        $namePayload = array_filter([
            'formatted_name' => $formattedName,
            'first_name' => filled($contact['first_name'] ?? null) ? trim((string) $contact['first_name']) : null,
            'last_name' => filled($contact['last_name'] ?? null) ? trim((string) $contact['last_name']) : null,
        ], fn ($value) => $value !== null && $value !== '');

        $phonePayload = array_filter([
            'phone' => $phone,
            'type' => $phoneType,
            'wa_id' => $waId,
        ], fn ($value) => $value !== null && $value !== '');

        $contactPayload = [
            'name' => $namePayload,
            'phones' => [$phonePayload],
        ];

        if (filled($contact['email'] ?? null)) {
            $contactPayload['emails'] = [[
                'email' => trim((string) $contact['email']),
                'type' => 'WORK',
            ]];
        }

        if (filled($contact['company'] ?? null)) {
            $contactPayload['org'] = [
                'company' => trim((string) $contact['company']),
            ];
        }

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $formattedName,
            messageType: MessageType::Contact,
            metadata: [
                'contacts' => [$contactPayload],
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
                'audit_status' => (string) ($template['AuditStatus'] ?? $template['auditStatus'] ?? ''),
                'reason' => (string) ($template['Reason'] ?? $template['reason'] ?? ''),
                'body' => (string) ($template['Body'] ?? $template['body'] ?? ''),
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
        // Legacy parity: low wallet blocks free-form, but templates / opt-in / payment still go out.
        if ($messageType !== MessageType::Template && $messageType !== MessageType::System) {
            $this->assertWalletAllowsSend();
        }

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

        try {
            app(InboxBroadcastService::class)->messageCreated($conversation->refresh(), $message);
        } catch (\Throwable $e) {
            Log::warning('Inbox outbound broadcast failed', [
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

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

    private function assertWalletAllowsSend(): void
    {
        $minBalance = (float) config('inbox.wallet_min_balance', 50);
        $balance = $this->walletService->balance();

        abort_if(
            $balance <= $minBalance,
            422,
            sprintf(
                'Insufficient wallet balance (₹%s). Please recharge to at least ₹%s to send messages.',
                number_format($balance, 2),
                number_format($minBalance, 2),
            ),
        );
    }
}
