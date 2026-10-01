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

        return [
            'id' => $conversation->id,
            'uuid' => $conversation->uuid,
            'customer_phone' => (string) $conversation->contact_phone,
            'customer_name' => (string) ($conversation->contact_name ?: $conversation->contact_phone),
            'whatsapp_number' => (string) ($conversation->line_phone ?: ''),
            'last_message' => $latest?->body,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => (int) $conversation->unread_count,
            'response_type' => $conversation->response_type?->value ?? 'human_response',
            'status' => $conversation->status?->value ?? 'open',
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
