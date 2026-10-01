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
