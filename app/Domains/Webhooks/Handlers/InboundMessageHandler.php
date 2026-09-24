<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Handlers;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Domains\AiBot\Services\AiInboundReplyService;
use App\Domains\Audience\Services\StopKeywordService;
use App\Domains\Campaigns\Services\CampaignInboundResponseService;
use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Domains\Commerce\Services\CommerceOrderIngestService;
use App\Domains\Inbox\Contracts\InboxServiceClientInterface;
use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxMessageService;
use App\Domains\TriggerTemplate\Enums\TriggerFireResult;
use App\Domains\TriggerTemplate\Services\TriggerTemplateEngine;
use App\Domains\Webhooks\Listeners\NewLeadWebhookListener;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInboundService;
use App\Enums\MessageType;
use App\Models\InboundWebhookEvent;
use App\Models\Message;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;

class InboundMessageHandler
{
    public function __construct(
        private readonly AlibabaWebhookParser $parser,
        private readonly WhatsappLineRegistryService $registryService,
        private readonly InboxConversationService $conversationService,
        private readonly InboxMessageService $messageService,
        private readonly TriggerTemplateEngine $triggerTemplateEngine,
        private readonly ChatbotFlowEngine $chatbotFlowEngine,
        private readonly NewLeadWebhookListener $newLeadWebhookListener,
        private readonly WhatsappFlowInboundService $whatsappFlowInboundService,
        private readonly CommerceOrderIngestService $commerceOrderIngest,
        private readonly StopKeywordService $stopKeywordService,
        private readonly AiInboundReplyService $aiInboundReplyService,
        private readonly CampaignInboundResponseService $campaignInboundResponseService,
    ) {}

    public function handle(InboundWebhookEvent $event): void
    {
        $items = $this->parser->parsePayload($event->payload);
        $processed = 0;
        $skipped = 0;
        $lastError = null;

        foreach ($items as $candidate) {
            if (! is_array($candidate) || ! $this->isInboundMessageItem($candidate)) {
                continue;
            }

            try {
                $result = $this->processItem($event, $candidate);
                if ($result === 'skipped') {
                    $skipped++;
                } else {
                    $processed++;
                }
            } catch (\Throwable $exception) {
                $lastError = $exception;
                Log::warning('Inbound message item failed', [
                    'event_id' => $event->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        // Unknown business phones are skipped (not errors). Only fail when nothing
        // was handled at all — empty payload or every item threw.
        if ($processed === 0 && $skipped === 0) {
            throw $lastError ?? new \RuntimeException('Inbound message payload is empty.');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return 'processed'|'skipped'
     */
    private function processItem(InboundWebhookEvent $event, array $item): string
    {
        $from = (string) ($item['From'] ?? $item['from'] ?? '');
        $to = (string) ($item['To'] ?? $item['to'] ?? '');
        $messageId = (string) ($item['MessageId'] ?? $item['messageId'] ?? $item['id'] ?? '');
        $parsedReply = $this->parseInboundMessage($item);
        $body = $parsedReply['body'];

        if ($from === '' || $to === '' || $messageId === '') {
            throw new \RuntimeException('Inbound message payload is missing From, To, or MessageId.');
        }

        $resolved = $this->registryService->resolveByBusinessPhone($to);
        $contactPhone = $from;

        if ($resolved === null) {
            $resolved = $this->registryService->resolveByBusinessPhone($from);
            $contactPhone = $to;
        }

        if ($resolved === null) {
            // Not a platform line (wrong route / unregistered / removed). Do not retry —
            // retrying cannot create a registry entry.
            Log::info('Skipping inbound message: no tenant registry for business phone', [
                'event_id' => $event->id,
                'to' => $to,
                'from' => $from,
                'message_id' => $messageId,
            ]);

            return 'skipped';
        }

        $wasInitialized = tenancy()->initialized;
        $previousTenant = $wasInitialized ? tenant() : null;
        tenancy()->initialize($resolved['tenant']);

        try {
            $line = WhatsappLine::query()->findOrFail($resolved['line_id']);
            $conversation = $this->conversationService->findOrCreateConversation(
                line: $line,
                contactPhone: $contactPhone,
                contactName: isset($item['Name']) && (string) $item['Name'] !== ''
                    ? (string) $item['Name']
                    : (isset($item['name']) ? (string) $item['name'] : null),
            );

            if (Message::query()->where('external_message_id', $messageId)->exists()) {
                $event->forceFill([
                    'tenant_id' => $resolved['tenant']->id,
                    'whatsapp_line_id' => $line->id,
                ])->save();

                return 'processed';
            }

            $messageType = $this->mapMessageType((string) ($item['Type'] ?? 'TEXT'));
            if ($parsedReply['is_interactive']) {
                $messageType = MessageType::Interactive;
            }

            $message = $this->messageService->recordInbound(
                conversation: $conversation,
                body: $body,
                externalMessageId: $messageId,
                messageType: $messageType,
            );

            try {
                app(\App\Domains\Alerts\Services\AlertDispatcher::class)->inboxNewMessage(
                    fromPhone: $contactPhone,
                    preview: $body !== '' ? $body : '['.$messageType->value.']',
                    contactName: $conversation->contact_name,
                );
            } catch (\Throwable $e) {
                Log::warning('Inbox owner alert failed', [
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($parsedReply['metadata'] !== []) {
                $message->forceFill([
                    'metadata' => array_merge(
                        is_array($message->metadata) ? $message->metadata : [],
                        $parsedReply['metadata'],
                    ),
                ])->save();
            }

            if ($messageType === MessageType::Order) {
                try {
                    $this->commerceOrderIngest->ingest($item, $line);
                } catch (\Throwable $e) {
                    Log::warning('Commerce order ingest failed', [
                        'message_id' => $messageId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // STOP / START keywords: unsubscribe or restart before chatbot/triggers.
            $keywordHandled = $this->stopKeywordService->handle($conversation->refresh(), $message);

            if (! $keywordHandled) {
                try {
                    $this->campaignInboundResponseService->recordReply($conversation, $message, $item);
                } catch (\Throwable $e) {
                    Log::warning('Campaign response tracking failed', [
                        'message_id' => $messageId,
                        'error' => $e->getMessage(),
                    ]);
                }

                // Chatbot keyword flows take priority over free trigger-templates
                // so the same word does not send template + wrong "next" chatbot message.
                $chatbotResult = TriggerFireResult::NoMatch;

                if (app(MaintenanceModeService::class)->moduleEnabled('chatbot')) {
                    $chatbotResult = $this->chatbotFlowEngine->processInbound($conversation->refresh(), $message);
                }

                if ($chatbotResult === TriggerFireResult::NoMatch) {
                    $triggerResult = $this->triggerTemplateEngine->process($conversation->refresh(), $message);
                } else {
                    $triggerResult = TriggerFireResult::NoMatch;
                }

                // AI only when chatbot / trigger-template did not fire a turn (legacy).
                // WalletBlocked / SendFailed must not permanently silence AI Assistant.
                if ($chatbotResult !== TriggerFireResult::Fired && $triggerResult !== TriggerFireResult::Fired) {
                    try {
                        $this->aiInboundReplyService->handle($conversation->refresh(), $message);
                    } catch (\Throwable $e) {
                        Log::warning('AI inbound reply hook failed', [
                            'message_id' => $messageId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

            $this->newLeadWebhookListener->handle($message, $conversation->refresh());

            if ($message->message_type === MessageType::Interactive) {
                $this->whatsappFlowInboundService->handleInteractiveMessage($message);
                }
            }

            $this->registryService->indexMessage(
                tenantId: $resolved['tenant']->id,
                externalMessageId: $messageId,
                messageId: $message->id,
            );

            if (config('inbox-service.enabled')) {
                try {
                    app(InboxServiceClientInterface::class)->recordInbound(
                        lineId: (int) $line->id,
                        contactPhone: $contactPhone,
                        body: $body,
                        contactName: isset($item['Name']) ? (string) $item['Name'] : null,
                        externalMessageId: $messageId,
                        messageType: $messageType->value,
                        linePhone: $line->phone,
                        contactId: (int) $conversation->contact_id,
                        metadata: $parsedReply['metadata'] !== [] ? $parsedReply['metadata'] : null,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed forwarding inbound message to inbox microservice', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $event->forceFill([
                'tenant_id' => $resolved['tenant']->id,
                'whatsapp_line_id' => $line->id,
            ])->save();

            return 'processed';
        } finally {
            if (! $wasInitialized) {
            tenancy()->end();
            } elseif ($previousTenant !== null && (string) $previousTenant->id !== (string) $resolved['tenant']->id) {
                tenancy()->initialize($previousTenant);
            }
        }
    }

    /**
     * Parse inbound Message field into a readable body + chatbot metadata.
     * Legacy parity: button_reply / list_reply titles drive routing; ids are stored separately.
     * Media types store CAMS `link` / `fileName` as metadata.media_url for inbox bubbles.
     *
     * @param  array<string, mixed>  $item
     * @return array{body: string, is_interactive: bool, metadata: array<string, mixed>}
     */
    private function parseInboundMessage(array $item): array
    {
        $type = strtoupper((string) ($item['Type'] ?? $item['type'] ?? 'MESSAGE'));
        $message = $item['Message'] ?? $item['message'] ?? $item['text'] ?? $item['body'] ?? null;

        if ($type === 'ORDER') {
            $messageData = is_string($message) ? json_decode($message, true) : (is_array($message) ? $message : null);
            $items = is_array($messageData) ? ($messageData['product_items'] ?? []) : [];
            $count = is_array($items) ? count($items) : 0;
            $total = is_array($items)
                ? collect($items)->sum(fn ($row) => ((float) ($row['item_price'] ?? 0)) * ((float) ($row['quantity'] ?? 1)))
                : 0;
            $currency = is_array($items) && isset($items[0]['currency']) ? strtoupper((string) $items[0]['currency']) : 'INR';

            return [
                'body' => sprintf('[ORDER] %d item(s) · %s %s', $count, $currency, number_format((float) $total, 2)),
                'is_interactive' => false,
                'metadata' => [],
            ];
        }

        $decoded = null;
        if (is_array($message)) {
            $decoded = $message;
        } elseif (is_string($message) && trim($message) !== '') {
            $trimmed = trim($message);
            if (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[') {
                $json = json_decode($trimmed, true);
                if (is_array($json)) {
                    $decoded = $json;
                }
            }
            if ($decoded === null) {
                $mediaMeta = $this->extractRichMediaMetadata($type, null, $trimmed);

                return [
                    'body' => $mediaMeta['caption'] !== '' ? $mediaMeta['caption'] : $trimmed,
                    'is_interactive' => false,
                    'metadata' => $mediaMeta['metadata'],
                ];
            }
        } else {
            $itemText = $this->extractReadableText($item);
            $mediaMeta = $this->extractRichMediaMetadata($type, is_array($item) ? $item : null, $itemText);

            return [
                'body' => $mediaMeta['caption'] !== ''
                    ? $mediaMeta['caption']
                    : ($itemText ?? ('['.$type.' message]')),
                'is_interactive' => false,
                'metadata' => $mediaMeta['metadata'],
            ];
        }

        $interactive = is_array($decoded['interactive'] ?? null) ? $decoded['interactive'] : null;
        $buttonReply = is_array($interactive['button_reply'] ?? null)
            ? $interactive['button_reply']
            : (is_array($decoded['button_reply'] ?? null) ? $decoded['button_reply'] : null);
        $listReply = is_array($interactive['list_reply'] ?? null)
            ? $interactive['list_reply']
            : (is_array($decoded['list_reply'] ?? null) ? $decoded['list_reply'] : null);
        $nfmReply = is_array($interactive['nfm_reply'] ?? null) ? $interactive['nfm_reply'] : null;

        if (is_array($buttonReply)) {
            $title = trim((string) ($buttonReply['title'] ?? $buttonReply['id'] ?? ''));
            $replyId = trim((string) ($buttonReply['id'] ?? ''));

            return [
                'body' => $title !== '' ? $title : '[Interactive reply]',
                'is_interactive' => true,
                'metadata' => array_filter([
                    'interactive_reply_id' => $replyId !== '' ? $replyId : null,
                    'reply_id' => $replyId !== '' ? $replyId : null,
                    'interactive_reply_type' => 'button_reply',
                    'interactive' => $interactive ?? ['button_reply' => $buttonReply],
                    'raw_message' => $decoded,
                ], fn ($v) => $v !== null),
            ];
        }

        if (is_array($listReply)) {
            $title = trim((string) ($listReply['title'] ?? $listReply['id'] ?? ''));
            $replyId = trim((string) ($listReply['id'] ?? ''));

            return [
                'body' => $title !== '' ? $title : '[Interactive reply]',
                'is_interactive' => true,
                'metadata' => array_filter([
                    'interactive_reply_id' => $replyId !== '' ? $replyId : null,
                    'reply_id' => $replyId !== '' ? $replyId : null,
                    'interactive_reply_type' => 'list_reply',
                    'interactive' => $interactive ?? ['list_reply' => $listReply],
                    'raw_message' => $decoded,
                ], fn ($v) => $v !== null),
            ];
        }

        if (is_array($nfmReply)) {
            return [
                'body' => trim((string) ($nfmReply['body'] ?? $nfmReply['name'] ?? '[Flow reply]')),
                'is_interactive' => true,
                'metadata' => [
                    'interactive_reply_type' => 'nfm_reply',
                    'interactive' => $interactive,
                    'raw_message' => $decoded,
                ],
            ];
        }

        // Quick-reply / template button payload shapes
        if (isset($decoded['button']) && is_array($decoded['button'])) {
            $title = trim((string) ($decoded['button']['text'] ?? $decoded['button']['payload'] ?? ''));
            $replyId = trim((string) ($decoded['button']['payload'] ?? ''));

            return [
                'body' => $title !== '' ? $title : '[Button reply]',
                'is_interactive' => true,
                'metadata' => array_filter([
                    'interactive_reply_id' => $replyId !== '' ? $replyId : null,
                    'reply_id' => $replyId !== '' ? $replyId : null,
                    'interactive_reply_type' => 'button',
                    'raw_message' => $decoded,
                ], fn ($v) => $v !== null),
            ];
        }

        if (isset($decoded['type']) && in_array((string) $decoded['type'], ['button_reply', 'list_reply'], true)) {
            $title = trim((string) ($decoded['title'] ?? $decoded['id'] ?? ''));
            $replyId = trim((string) ($decoded['id'] ?? ''));

            return [
                'body' => $title !== '' ? $title : '[Interactive reply]',
                'is_interactive' => true,
                'metadata' => array_filter([
                    'interactive_reply_id' => $replyId !== '' ? $replyId : null,
                    'reply_id' => $replyId !== '' ? $replyId : null,
                    'interactive_reply_type' => (string) $decoded['type'],
                    'raw_message' => $decoded,
                ], fn ($v) => $v !== null),
            ];
        }

        $mediaMeta = $this->extractRichMediaMetadata($type, $decoded, null);
        $readable = $mediaMeta['caption'] !== ''
            ? $mediaMeta['caption']
            : $this->extractReadableText($decoded);

        if ($readable !== null || $mediaMeta['metadata'] !== []) {
            return [
                'body' => $readable ?? ('['.$type.' message]'),
                'is_interactive' => false,
                'metadata' => array_filter(array_merge(
                    ['raw_message' => $decoded],
                    $mediaMeta['metadata'],
                ), fn ($v) => $v !== null && $v !== []),
            ];
        }

        if (is_string($message) && trim($message) !== '' && ! $this->looksLikeJsonObject(trim($message))) {
            return [
                'body' => trim($message),
                'is_interactive' => false,
                'metadata' => [],
            ];
        }

        $itemText = $this->extractReadableText($item);
        if ($itemText !== null) {
            return [
                'body' => $itemText,
                'is_interactive' => false,
                'metadata' => is_array($decoded) ? ['raw_message' => $decoded] : [],
            ];
        }

        return [
            'body' => '['.$type.' message]',
            'is_interactive' => false,
            'metadata' => is_array($decoded) ? ['raw_message' => $decoded] : [],
        ];
    }

    /**
     * Promote CAMS / Cloud API media, location, and contact fields into inbox metadata.
     *
     * @return array{caption: string, metadata: array<string, mixed>}
     */
    private function extractRichMediaMetadata(string $type, ?array $decoded, ?string $plainBody): array
    {
        $type = strtoupper($type);
        $metadata = [];
        $caption = '';

        $mediaTypes = ['IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT', 'STICKER'];
        $locationTypes = ['LOCATION'];
        $contactTypes = ['CONTACT', 'CONTACTS'];

        if (in_array($type, $mediaTypes, true)) {
            $link = $this->firstNonEmptyString(
                $decoded['link'] ?? null,
                $decoded['url'] ?? null,
                $decoded['media_url'] ?? null,
                is_array($decoded['image'] ?? null) ? ($decoded['image']['link'] ?? $decoded['image']['url'] ?? null) : null,
                is_array($decoded['video'] ?? null) ? ($decoded['video']['link'] ?? $decoded['video']['url'] ?? null) : null,
                is_array($decoded['audio'] ?? null) ? ($decoded['audio']['link'] ?? $decoded['audio']['url'] ?? null) : null,
                is_array($decoded['document'] ?? null) ? ($decoded['document']['link'] ?? $decoded['document']['url'] ?? null) : null,
                is_array($decoded['sticker'] ?? null) ? ($decoded['sticker']['link'] ?? $decoded['sticker']['url'] ?? null) : null,
            );

            if ($link === null && is_string($plainBody) && $this->looksLikeHttpUrl($plainBody)) {
                $link = $plainBody;
            }

            if ($link !== null) {
                $metadata['media_url'] = $link;
            }

            $fileName = $this->firstNonEmptyString(
                $decoded['fileName'] ?? null,
                $decoded['filename'] ?? null,
                $decoded['file_name'] ?? null,
                is_array($decoded['document'] ?? null) ? ($decoded['document']['filename'] ?? $decoded['document']['fileName'] ?? null) : null,
            );
            if ($fileName !== null) {
                $metadata['file_name'] = $fileName;
            }

            $fileType = $this->firstNonEmptyString(
                $decoded['fileType'] ?? null,
                $decoded['mime_type'] ?? null,
                $decoded['file_type'] ?? null,
                is_array($decoded['document'] ?? null) ? ($decoded['document']['mime_type'] ?? null) : null,
            );
            if ($fileType !== null) {
                $metadata['file_type'] = $fileType;
            }

            $caption = (string) ($this->firstNonEmptyString(
                $decoded['text'] ?? null,
                $decoded['caption'] ?? null,
                is_array($decoded['image'] ?? null) ? ($decoded['image']['caption'] ?? null) : null,
                is_array($decoded['video'] ?? null) ? ($decoded['video']['caption'] ?? null) : null,
                is_array($decoded['document'] ?? null) ? ($decoded['document']['caption'] ?? null) : null,
            ) ?? '');

            // Plain body that is only the media URL should not become the caption.
            if ($caption === '' && is_string($plainBody) && ! $this->looksLikeHttpUrl($plainBody)) {
                $caption = $plainBody;
            }
        }

        if (in_array($type, $locationTypes, true) && is_array($decoded)) {
            $lat = $decoded['latitude'] ?? $decoded['lat']
                ?? (is_array($decoded['location'] ?? null) ? ($decoded['location']['latitude'] ?? null) : null);
            $lng = $decoded['longitude'] ?? $decoded['lng'] ?? $decoded['long']
                ?? (is_array($decoded['location'] ?? null) ? ($decoded['location']['longitude'] ?? null) : null);

            if (is_numeric($lat) && is_numeric($lng)) {
                $metadata['latitude'] = (float) $lat;
                $metadata['longitude'] = (float) $lng;
                $caption = sprintf('%s, %s', $lat, $lng);
            }
        }

        if (in_array($type, $contactTypes, true) && is_array($decoded)) {
            $contacts = $decoded['contacts'] ?? null;
            if (! is_array($contacts) && isset($decoded['name'])) {
                $contacts = [$decoded];
            }
            if (is_array($contacts) && $contacts !== []) {
                $metadata['contacts'] = $contacts;
                $first = $contacts[0] ?? null;
                if (is_array($first)) {
                    $caption = (string) ($this->firstNonEmptyString(
                        is_array($first['name'] ?? null) ? ($first['name']['formatted_name'] ?? $first['name']['first_name'] ?? null) : null,
                        is_string($first['name'] ?? null) ? $first['name'] : null,
                    ) ?? 'Contact');
                }
            }
        }

        return [
            'caption' => $caption,
            'metadata' => $metadata,
        ];
    }

    private function firstNonEmptyString(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    private function looksLikeHttpUrl(string $value): bool
    {
        return (bool) preg_match('#^https?://#i', trim($value));
    }

    /**
     * @param  array<string, mixed>|mixed  $payload
     */
    private function extractReadableText(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $trimmed = trim($payload);

            return $trimmed !== '' && ! $this->looksLikeJsonObject($trimmed) ? $trimmed : null;
        }

        if (! is_array($payload)) {
            return null;
        }

        if (isset($payload['text']) && is_string($payload['text']) && trim($payload['text']) !== '') {
            return trim($payload['text']);
        }

        if (isset($payload['text']) && is_array($payload['text'])) {
            foreach (['body', 'text', 'message'] as $key) {
                $inner = $payload['text'][$key] ?? null;
                if (is_string($inner) && trim($inner) !== '') {
                    return trim($inner);
                }
            }
        }

        foreach (['body', 'Body', 'message', 'Message', 'caption', 'Caption'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '' && ! $this->looksLikeJsonObject(trim($value))) {
                return trim($value);
            }
            if (is_array($value)) {
                $nested = $this->extractReadableText($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    private function looksLikeJsonObject(string $value): bool
    {
        $start = $value[0] ?? '';

        return $start === '{' || $start === '[';
    }

    private function isInboundMessageItem(array $item): bool
    {
        $from = (string) ($item['From'] ?? $item['from'] ?? '');
        $to = (string) ($item['To'] ?? $item['to'] ?? '');
        $messageId = (string) ($item['MessageId'] ?? $item['messageId'] ?? $item['id'] ?? '');
        $status = trim((string) ($item['Status'] ?? $item['status'] ?? ''));

        return $from !== '' && $to !== '' && $messageId !== '' && $status === '';
    }

    private function mapMessageType(string $type): MessageType
    {
        return match (strtoupper($type)) {
            'IMAGE' => MessageType::Image,
            'VIDEO' => MessageType::Video,
            'AUDIO' => MessageType::Audio,
            'DOCUMENT' => MessageType::Document,
            'INTERACTIVE', 'REPLY' => MessageType::Interactive,
            'LOCATION' => MessageType::Location,
            'CONTACT', 'CONTACTS' => MessageType::Contact,
            'STICKER' => MessageType::Sticker,
            'ORDER' => MessageType::Order,
            default => MessageType::Text,
        };
    }
}
