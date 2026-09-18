<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Handlers;

use App\Domains\Admin\Services\MaintenanceModeService;
use App\Domains\Audience\Services\StopKeywordService;
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
    ) {}

    public function handle(InboundWebhookEvent $event): void
    {
        $items = $this->parser->parsePayload($event->payload);
        $processed = 0;
        $lastError = null;

        foreach ($items as $candidate) {
            if (! is_array($candidate) || ! $this->isInboundMessageItem($candidate)) {
                continue;
            }

            try {
                $this->processItem($event, $candidate);
                $processed++;
            } catch (\Throwable $exception) {
                $lastError = $exception;
                Log::warning('Inbound message item failed', [
                    'event_id' => $event->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($processed === 0) {
            throw $lastError ?? new \RuntimeException('Inbound message payload is empty.');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function processItem(InboundWebhookEvent $event, array $item): void
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
            throw new \RuntimeException('No tenant registry entry found for business phone '.$to);
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

                return;
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
                // Chatbot keyword flows take priority over free trigger-templates
                // so the same word does not send template + wrong "next" chatbot message.
                $chatbotResult = TriggerFireResult::NoMatch;

                if (app(MaintenanceModeService::class)->moduleEnabled('chatbot')) {
                    $chatbotResult = $this->chatbotFlowEngine->processInbound($conversation->refresh(), $message);
                }

                if ($chatbotResult === TriggerFireResult::NoMatch) {
                    $this->triggerTemplateEngine->process($conversation->refresh(), $message);
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
                return [
                    'body' => $trimmed,
                    'is_interactive' => false,
                    'metadata' => [],
                ];
            }
        } else {
            $itemText = $this->extractReadableText($item);

            return [
                'body' => $itemText ?? ('['.$type.' message]'),
                'is_interactive' => false,
                'metadata' => [],
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

        $readable = $this->extractReadableText($decoded);
        if ($readable !== null) {
            return [
                'body' => $readable,
                'is_interactive' => false,
                'metadata' => ['raw_message' => $decoded],
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
            'CONTACT' => MessageType::Contact,
            'STICKER' => MessageType::Sticker,
            'ORDER' => MessageType::Order,
            default => MessageType::Text,
        };
    }
}
