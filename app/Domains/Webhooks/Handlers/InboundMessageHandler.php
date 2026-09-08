<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Handlers;

use App\Domains\Chatbot\Services\ChatbotFlowEngine;
use App\Domains\Commerce\Services\CommerceOrderIngestService;
use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\Inbox\Services\InboxMessageService;
use App\Domains\TriggerTemplate\Services\TriggerTemplateEngine;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInboundService;
use App\Domains\Webhooks\Listeners\NewLeadWebhookListener;
use App\Domains\Webhooks\Parsers\AlibabaWebhookParser;
use App\Domains\Webhooks\Services\WhatsappLineRegistryService;
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
    ) {}

    public function handle(InboundWebhookEvent $event): void
    {
        $items = $this->parser->parsePayload($event->payload);
        $item = $this->parser->firstItem($items);

        if ($item === null) {
            throw new \RuntimeException('Inbound message payload is empty.');
        }

        $from = (string) ($item['From'] ?? '');
        $to = (string) ($item['To'] ?? '');
        $messageId = (string) ($item['MessageId'] ?? '');
        $body = $this->extractBody($item);

        if ($from === '' || $to === '' || $messageId === '') {
            throw new \RuntimeException('Inbound message payload is missing From, To, or MessageId.');
        }

        $resolved = $this->registryService->resolveByBusinessPhone($to);

        if ($resolved === null) {
            throw new \RuntimeException('No tenant registry entry found for business phone '.$to);
        }

        tenancy()->initialize($resolved['tenant']);

        try {
            $line = WhatsappLine::query()->findOrFail($resolved['line_id']);
            $conversation = $this->conversationService->findOrCreateConversation(
                line: $line,
                contactPhone: $from,
                contactName: isset($item['Name']) ? (string) $item['Name'] : null,
            );

            if (Message::query()->where('external_message_id', $messageId)->exists()) {
                $event->forceFill([
                    'tenant_id' => $resolved['tenant']->id,
                    'whatsapp_line_id' => $line->id,
                ])->save();

                return;
            }

            $messageType = $this->mapMessageType((string) ($item['Type'] ?? 'TEXT'));

            $message = $this->messageService->recordInbound(
                conversation: $conversation,
                body: $body,
                externalMessageId: $messageId,
                messageType: $messageType,
            );

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

            $this->triggerTemplateEngine->process($conversation->refresh(), $message);

            $this->chatbotFlowEngine->processInbound($conversation->refresh(), $message);
            $this->newLeadWebhookListener->handle($message, $conversation->refresh());

            if ($message->message_type === MessageType::Interactive) {
                $this->whatsappFlowInboundService->handleInteractiveMessage($message);
            }

            $this->registryService->indexMessage(
                tenantId: $resolved['tenant']->id,
                externalMessageId: $messageId,
                messageId: $message->id,
            );

            if (config('inbox-service.enabled')) {
                try {
                    app(\App\Domains\Inbox\Contracts\InboxServiceClientInterface::class)->recordInbound(
                        lineId: (int) $line->id,
                        contactPhone: $from,
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
            tenancy()->end();
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractBody(array $item): string
    {
        $type = strtoupper((string) ($item['Type'] ?? 'MESSAGE'));
        $message = $item['Message'] ?? null;

        if ($type === 'ORDER') {
            $messageData = is_string($message) ? json_decode($message, true) : (is_array($message) ? $message : null);
            $items = is_array($messageData) ? ($messageData['product_items'] ?? []) : [];
            $count = is_array($items) ? count($items) : 0;
            $total = is_array($items)
                ? collect($items)->sum(fn ($row) => ((float) ($row['item_price'] ?? 0)) * ((float) ($row['quantity'] ?? 1)))
                : 0;
            $currency = is_array($items) && isset($items[0]['currency']) ? strtoupper((string) $items[0]['currency']) : 'INR';

            return sprintf('[ORDER] %d item(s) · %s %s', $count, $currency, number_format((float) $total, 2));
        }

        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        return '['.$type.' message]';
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
