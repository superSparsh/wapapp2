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
            MessageType::Contact => $this->buildContactPayload($message, $payload),
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
        $templateParams = is_array($metadata['template_params'] ?? null)
            ? $metadata['template_params']
            : [];
        $language = CamsTemplateIdentity::language(
            (string) ($metadata['language'] ?? $payload['Language'] ?? ''),
        );

        // Defensive: flatten nested body/header maps if any caller still sends them.
        $flatParams = [];
        foreach ($templateParams as $key => $value) {
            if (is_array($value) && in_array((string) $key, ['body', 'header', 'footer', 'buttons'], true)) {
                foreach ($value as $innerKey => $innerValue) {
                    if (is_scalar($innerValue) || $innerValue === null) {
                        $flatParams[(string) $innerKey] = trim((string) $innerValue);
                    }
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $flatParams[(string) $key] = trim((string) $value);
            }
        }

        return array_merge($payload, [
            'Type' => 'template',
            'Language' => $language,
            'TemplateCode' => $templateCode,
            'TemplateParams' => json_encode((object) $flatParams, JSON_THROW_ON_ERROR),
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

    /**
     * @param  array<string, string>  $payload
     * @return array<string, string>
     */
    private function buildContactPayload(Message $message, array $payload): array
    {
        $metadata = $message->metadata ?? [];
        $contacts = is_array($metadata['contacts'] ?? null) ? $metadata['contacts'] : [];

        // Legacy TeamInboxMessageService::sendContactsWithResponse:
        // Content = json_encode([$contact, ...]) — bare JSON array of contact objects.
        // Alibaba params page: "Contacts must be passed as an array".
        // Each contact needs name.formatted_name + ≥1 optional name field.
        $normalized = [];
        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }
            $normalized[] = $this->normalizeContactForCams($contact);
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('Contact details are incomplete. Add a name and phone number.');
        }

        // Match legacy free-form contact request (no Language on SendChatappMessageRequest).
        unset($payload['Language']);

        return array_merge($payload, [
            'Type' => 'message',
            'MessageType' => 'contacts',
            'Content' => json_encode(array_values($normalized), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function normalizeContactForCams(array $contact): array
    {
        $name = is_array($contact['name'] ?? null) ? $contact['name'] : [];
        $formatted = trim((string) ($name['formatted_name'] ?? ''));
        $first = trim((string) ($name['first_name'] ?? ''));
        $last = trim((string) ($name['last_name'] ?? ''));
        $middle = trim((string) ($name['middle_name'] ?? ''));
        $prefix = trim((string) ($name['prefix'] ?? ''));
        $suffix = trim((string) ($name['suffix'] ?? ''));

        if ($formatted === '' && ($first !== '' || $last !== '')) {
            $formatted = trim($first.' '.$last);
        }

        // CAMS/Meta: formatted_name must include ≥1 optional name field.
        if ($first === '' && $last === '' && $middle === '' && $prefix === '' && $suffix === '' && $formatted !== '') {
            $parts = preg_split('/\s+/', $formatted, 2) ?: [];
            $first = (string) ($parts[0] ?? $formatted);
            $last = isset($parts[1]) ? trim((string) $parts[1]) : '';
        }

        // Single-word names still need a companion field — reuse as first_name.
        if ($first === '' && $formatted !== '') {
            $first = $formatted;
        }

        $namePayload = array_filter([
            'formatted_name' => $formatted,
            'first_name' => $first !== '' ? $first : null,
            'last_name' => $last !== '' ? $last : null,
            'middle_name' => $middle !== '' ? $middle : null,
            'prefix' => $prefix !== '' ? $prefix : null,
            'suffix' => $suffix !== '' ? $suffix : null,
        ], fn ($value) => $value !== null && $value !== '');

        $phones = [];
        foreach (is_array($contact['phones'] ?? null) ? $contact['phones'] : [] as $phoneRow) {
            if (! is_array($phoneRow)) {
                continue;
            }
            $raw = (string) ($phoneRow['phone'] ?? $phoneRow['wa_id'] ?? '');
            $digits = PhoneNormalizer::normalize($raw)
                ?? (preg_replace('/\D+/', '', $raw) ?: '');
            if ($digits === '') {
                continue;
            }
            $type = strtoupper(trim((string) ($phoneRow['type'] ?? 'CELL')));
            if (! in_array($type, ['CELL', 'MAIN', 'IPHONE', 'HOME', 'WORK'], true)) {
                $type = 'CELL';
            }
            // Meta Cloud API examples use display phone with "+" and digits-only wa_id.
            $phones[] = [
                'phone' => '+'.$digits,
                'type' => $type,
                'wa_id' => $digits,
            ];
        }

        if ($phones === [] || ($namePayload['formatted_name'] ?? '') === '') {
            throw new \InvalidArgumentException('Contact details are incomplete. Add a name and phone number.');
        }

        // Minimal card: name + phones only (emails/org optional and previously noisy).
        return [
            'name' => $namePayload,
            'phones' => $phones,
        ];
    }

    private function formatRecipient(string $phone): string
    {
        // Alibaba CAMS requires From/To as digits only (InvalidParameter.FromOnlyNumeric).
        return PhoneNormalizer::normalize($phone)
            ?? (preg_replace('/\D+/', '', $phone) ?? '');
    }
}
