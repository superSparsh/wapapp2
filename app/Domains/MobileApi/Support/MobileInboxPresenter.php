<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;

final class MobileInboxPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function line(WhatsappLine $line): array
    {
        return [
            'id' => $line->id,
            'uuid' => $line->uuid,
            'phone' => (string) $line->phone,
            'whatsapp_number' => (string) $line->phone,
            'display_name' => $line->displayLabel(),
            'is_default' => (bool) $line->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function conversation(Conversation $conversation): array
    {
        $latest = $conversation->relationLoaded('latestMessage')
            ? $conversation->latestMessage
            : $conversation->latestMessage()->first();

        $isAi = $conversation->response_type?->isAi() ?? false;
        $customerPhone = (string) $conversation->contact_phone;
        $customerName = (string) ($conversation->contact_name ?: $conversation->contact_phone);
        $linePhone = (string) ($conversation->line_phone ?: '');
        $latestBody = $latest?->body;
        $latestAt = ($latest?->created_at ?? $conversation->last_message_at)?->toIso8601String();
        $msgType = $latest?->message_type?->value ?? 'text';

        return [
            // 2.0 / docs fields
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            'customer_phone' => $customerPhone,
            'customer_name' => $customerName,
            'whatsapp_number' => $linePhone,
            'last_message' => $latestBody,
            'last_message_time' => ($conversation->last_message_at ?? $latest?->created_at)?->toIso8601String(),
            'latest_msg' => $latestBody,
            'latest_msg_type' => $msgType,
            'latest_msg_time' => $latestAt,
            'unread_count' => (int) $conversation->unread_count,
            'response_type' => $conversation->response_type?->value ?? 'human_response',
            'status' => $conversation->status?->value ?? 'open',
            'is_ai_enabled' => $isAi,
            // Legacy leftDataForOpen / SubReply field aliases (mobile app parsers)
            'sender_name' => $customerName,
            'msg_from' => $customerPhone,
            'msg_to' => $linePhone,
            'msg_created_at' => $latestAt,
            'msg_type' => $msgType,
            'msg_status' => $latest?->status?->value ?? null,
        ];
    }

    /**
     * Legacy mobile chat bubble fields (`frnd` = inbound, `my` = outbound).
     *
     * @return array<string, mixed>
     */
    public static function legacyChatMessage(Message $message, Conversation $conversation): array
    {
        $direction = $message->direction?->value ?? 'outbound';
        $isInbound = $direction === 'inbound';
        $type = $isInbound ? 'frnd' : 'my';
        $body = (string) ($message->body ?? '');
        $createdAt = $message->created_at?->toIso8601String();
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $mediaUrl = $metadata['media_url'] ?? $metadata['media_url_local'] ?? null;

        return [
            'id' => (int) $message->id,
            'sub_reply_id' => (int) $conversation->id,
            'msg' => $body,
            'message' => $body,
            'body' => $body,
            'type' => $type,
            'direction' => $isInbound ? 'incoming' : 'outgoing',
            'msg_from' => $isInbound ? (string) $conversation->contact_phone : (string) ($conversation->line_phone ?? ''),
            'msg_to' => $isInbound ? (string) ($conversation->line_phone ?? '') : (string) $conversation->contact_phone,
            'message_type' => $message->message_type?->value ?? 'text',
            'media_url' => $mediaUrl,
            'media_type' => $metadata['file_type'] ?? null,
            'message_id' => $message->external_message_id ?: (string) $message->id,
            'status' => $message->status?->value ?? 'sent',
            'is_read' => $message->read_at !== null || ($message->status?->value === 'read'),
            'timestamp' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $message->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<int, Message>  $messages
     * @return list<array<string, mixed>>
     */
    public static function legacyChatMessages(iterable $messages, Conversation $conversation): array
    {
        $out = [];
        foreach ($messages as $message) {
            if ($message instanceof Message) {
                $out[] = self::legacyChatMessage($message, $conversation);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function assignedNumber(WhatsappLine $line): array
    {
        $raw = (string) $line->phone;
        $phone = preg_replace('/\D+/', '', $raw) ?: $raw;

        return [
            'id' => $line->id,
            'uuid' => $line->uuid,
            'phone' => $phone,
            'value' => $phone,
            'whatsapp_number' => $phone,
            'verified_name' => $line->display_name,
            'display_name' => $line->displayLabel(),
            'label' => $line->displayLabel(),
            'status' => 'active',
            'is_default' => (bool) $line->is_default,
            'assigned_at' => $line->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(Message $message): array
    {
        return [
            'id' => $message->id,
            'message_id' => $message->external_message_id ?: (string) $message->id,
            'body' => $message->body,
            'direction' => $message->direction?->value ?? 'outbound',
            'message_type' => $message->message_type?->value ?? 'text',
            'status' => $message->status?->value ?? 'queued',
            'timestamp' => ($message->sent_at ?? $message->created_at)?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        return PhoneNormalizer::normalize($phone) ?? preg_replace('/\D+/', '', $phone);
    }
}
