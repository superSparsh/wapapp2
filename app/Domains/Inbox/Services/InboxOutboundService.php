<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Domains\Billing\Services\WalletService;
use App\Domains\Inbox\Jobs\SendOutboundMessageJob;
use App\Domains\Templates\Services\TemplatePreviewService;
use App\Domains\Templates\Services\TemplateRegistryService;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Template;
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
        bool $sendImmediately = false,
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
            sendImmediately: $sendImmediately,
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

    public function sendSticker(
        Conversation $conversation,
        UploadedFile $file,
        bool $sendImmediately = false,
    ): Message {
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
            sendImmediately: $sendImmediately,
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
     * @param  array<string, mixed>  $extraMetadata
     */
    public function sendTemplate(
        Conversation $conversation,
        string $templateCode,
        array $templateParams = [],
        ?string $language = null,
        bool $sendImmediately = false,
        array $extraMetadata = [],
    ): Message {
        $templateCode = trim($templateCode);
        abort_if($templateCode === '', 422, 'Template code is required.');

        $conversation->loadMissing('whatsappLine');
        $template = app(TemplateRegistryService::class)->findForSend(
            $templateCode,
            $conversation->whatsappLine,
        );

        $providerCode = $template?->whatsappCode();
        if ($providerCode === null && CamsTemplateIdentity::isProviderCode($templateCode)) {
            $providerCode = $templateCode;
        }

        abort_if(
            $providerCode === null,
            422,
            'This template is not approved on WhatsApp yet. Refresh templates and select an approved TemplateCode.',
        );

        $templateParams = $this->flattenTemplateParams($templateParams);
        $resolvedLanguage = CamsTemplateIdentity::language(
            $language ?? $template?->language ?? config('whatsapp.alibaba.default_language', 'en_GB'),
        );

        $display = $this->resolveTemplateDisplay(
            $providerCode,
            $templateParams,
            $conversation->whatsappLine,
        );

        return $this->createOutboundMessage(
            conversation: $conversation,
            body: $display['body'],
            messageType: MessageType::Template,
            metadata: array_merge([
                'template_code' => $providerCode,
                'template_name' => $display['name'] ?? $template?->name,
                'template_params' => $templateParams,
                'template_buttons' => $display['buttons'],
                'language' => $resolvedLanguage,
                'template_category' => strtoupper((string) ($template?->category ?? 'MARKETING')),
                'template_id' => $template?->id,
                'billable' => true,
                'wallet_source' => 'inbox',
            ], $extraMetadata),
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
     * Falls back to legacy "..." text ping when the typing_indicator API is unavailable.
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
            'Content' => json_encode(['text' => '...'], JSON_UNESCAPED_UNICODE),
        ];

        try {
            $response = $this->camsClient->sendChatappMessage($params);

            if ($response->successful()) {
                return true;
            }

            Log::info('CAMS typing_indicator rejected; falling back to legacy text ping', [
                'conversation_id' => $conversation->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send WhatsApp typing_indicator via CAMS: '.$e->getMessage(), [
                'conversation_id' => $conversation->id,
            ]);
        }

        // Legacy ChatbotFlowService::sendAlibabaTypingIndicator — literal "..." text.
        try {
            $fallback = [
                'From' => $from,
                'To' => $to,
                'Type' => 'message',
                'MessageType' => 'text',
                'CustSpaceId' => $line->alibaba_cust_space_id,
                'Content' => json_encode(['text' => '...'], JSON_UNESCAPED_UNICODE),
            ];
            $response = $this->camsClient->sendChatappMessage($fallback);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Failed to send legacy typing text via CAMS: '.$e->getMessage(), [
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
            $metadata = array_merge([
                'billable' => true,
                'wallet_source' => 'inbox',
                'pricing_category' => 'SERVICE',
            ], $metadata ?? []);
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

    /**
     * @param  array<string, mixed>  $templateParams
     * @return array{body: string, name: ?string, buttons: list<array{text: string, type: string}>}
     */
    private function resolveTemplateDisplay(string $templateCode, array $templateParams, ?WhatsappLine $line): array
    {
        $template = app(TemplateRegistryService::class)->findForSend($templateCode, $line);

        if (! $template instanceof Template) {
            return [
                'body' => $templateCode,
                'name' => null,
                'buttons' => [],
            ];
        }

        $preview = app(TemplatePreviewService::class)->forTemplate($template, $templateParams, false);

        $body = trim((string) ($preview['body'] ?? ''));
        if ($body === '') {
            $body = trim((string) ($template->body_preview ?: $template->name ?: $templateCode));
        }

        $buttons = [];
        foreach (is_array($preview['buttons'] ?? null) ? $preview['buttons'] : [] as $button) {
            if (! is_array($button)) {
                continue;
            }

            $text = trim((string) ($button['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $buttons[] = [
                'text' => $text,
                'type' => (string) ($button['type'] ?? 'url'),
            ];
        }

        return [
            'body' => $body,
            'name' => trim((string) $template->name) !== '' ? (string) $template->name : null,
            'buttons' => $buttons,
        ];
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

    /**
     * CAMS TemplateParams must be a flat string map (not nested body/header objects).
     *
     * @param  array<string, mixed>  $templateParams
     * @return array<string, string>
     */
    private function flattenTemplateParams(array $templateParams): array
    {
        $flat = [];

        foreach ($templateParams as $key => $value) {
            if (is_array($value)) {
                $isAssoc = Arr::isAssoc($value);
                if ($isAssoc && in_array((string) $key, ['body', 'header', 'footer', 'buttons'], true)) {
                    foreach ($value as $innerKey => $innerValue) {
                        if (is_scalar($innerValue) || $innerValue === null) {
                            $flat[(string) $innerKey] = trim((string) $innerValue);
                        }
                    }

                    continue;
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flat[(string) $key] = trim((string) $value);
            }
        }

        return $flat;
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
