<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Enums\MessageType;
use App\Domains\Templates\Support\CamsTemplateIdentity;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;

class CamsOutboundPayloadBuilder
{
    /**
     * @return array<string, string>
     */
    public function build(Message $message, Conversation $conversation, WhatsappLine $line): array
    {
        $message->loadMissing('conversation.whatsappLine');

        $to = $this->formatRecipient($conversation->contact_phone);
        $from = $this->formatRecipient($line->phone);

        $payload = [
            'From' => $from,
            'To' => $to,
            'Language' => (string) config('whatsapp.alibaba.default_language', 'en_GB'),
        ];

        if (filled($line->alibaba_cust_space_id)) {
            $payload['CustSpaceId'] = $line->alibaba_cust_space_id;
        }

        return match ($message->message_type) {
            MessageType::Template => $this->buildTemplatePayload($message, $payload),
            MessageType::Interactive => $this->buildInteractivePayload($message, $payload),
            MessageType::Image, MessageType::Video, MessageType::Audio, MessageType::Document => $this->buildMediaPayload($message, $payload),
            MessageType::Location => $this->buildLocationPayload($message, $payload),
            MessageType::Sticker => $this->buildStickerPayload($message, $payload),
            default => $this->buildTextPayload($message, $payload),
        };
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function buildTextPayload(Message $message, array $payload): array
    {
        return array_merge($payload, [
            'Type' => 'message',
            'MessageType' => 'text',
            'Content' => json_encode([
                'text' => (string) $message->body,
                'link' => '',
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function buildTemplatePayload(Message $message, array $payload): array
    {
        $metadata = $message->metadata ?? [];
        $templateCode = (string) ($metadata['template_code'] ?? '');
        $templateParams = $metadata['template_params'] ?? [];
        $language = CamsTemplateIdentity::language(
            (string) ($metadata['language'] ?? $payload['Language'] ?? ''),
        );

        return array_merge($payload, [
            'Type' => 'template',
            'Language' => $language,
            'TemplateCode' => $templateCode,
            'TemplateParams' => json_encode((object) $templateParams, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function buildInteractivePayload(Message $message, array $payload): array
    {
        $metadata = $message->metadata ?? [];
        $interactive = is_array($metadata['interactive'] ?? null) ? $metadata['interactive'] : [];

        return array_merge($payload, [
            'Type' => 'message',
            'MessageType' => 'interactive',
            'Content' => json_encode($interactive, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function buildMediaPayload(Message $message, array $payload): array
    {
        $metadata = $message->metadata ?? [];
        $link = (string) ($metadata['media_url'] ?? '');
        $caption = (string) ($message->body ?? '');
        $messageType = match ($message->message_type) {
            MessageType::Video => 'video',
            MessageType::Audio => 'audio',
            MessageType::Document => 'document',
            default => 'image',
        };

        $content = match ($message->message_type) {
            MessageType::Document => [
                'link' => $link,
                'fileName' => (string) ($metadata['file_name'] ?? 'file'),
                'fileType' => (string) ($metadata['file_type'] ?? 'application/octet-stream'),
            ],
            MessageType::Video => [
                'text' => $caption,
                'link' => $link,
                'thumbnail' => (string) ($metadata['thumbnail_url'] ?? ''),
            ],
            default => [
                'text' => $caption,
                'link' => $link,
            ],
        };

        return array_merge($payload, [
            'Type' => 'message',
            'MessageType' => $messageType,
            'Content' => json_encode($content, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function buildLocationPayload(Message $message, array $payload): array
    {
        $metadata = $message->metadata ?? [];

        return array_merge($payload, [
            'Type' => 'message',
            'MessageType' => 'location',
            'Content' => json_encode([
                'latitude' => (string) ($metadata['latitude'] ?? '0'),
                'longitude' => (string) ($metadata['longitude'] ?? '0'),
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function buildStickerPayload(Message $message, array $payload): array
    {
        $metadata = $message->metadata ?? [];

        return array_merge($payload, [
            'Type' => 'message',
            'MessageType' => 'sticker',
            'Content' => json_encode([
                'link' => (string) ($metadata['media_url'] ?? ''),
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function formatRecipient(string $phone): string
    {
        // Alibaba CAMS requires From/To as digits only (InvalidParameter.FromOnlyNumeric).
        return PhoneNormalizer::normalize($phone)
            ?? (preg_replace('/\D+/', '', $phone) ?? '');
    }
}
