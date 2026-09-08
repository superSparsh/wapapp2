<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConversationResponseType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class InboxContactService
{
    public function addContact(
        int $lineId,
        ?string $linePhone,
        string $name,
        string $phone,
        ConversationResponseType $responseType = ConversationResponseType::Human,
        ?int $contactId = null,
    ): Conversation {
        $normalizedPhone = PhoneNormalizer::normalize($phone) ?? preg_replace('/\D+/', '', $phone);
        $normalizedLinePhone = PhoneNormalizer::normalize($linePhone) ?? $linePhone;

        abort_if($normalizedPhone === '' || $normalizedPhone === null, 422, 'A valid phone number is required.');

        $existing = Conversation::query()
            ->where('whatsapp_line_id', $lineId)
            ->where('contact_phone', $normalizedPhone)
            ->first();

        abort_if($existing !== null, 422, 'Contact already exists.');

        return DB::transaction(function () use ($lineId, $normalizedLinePhone, $name, $normalizedPhone, $responseType, $contactId): Conversation {
            $conversation = Conversation::query()->create([
                'whatsapp_line_id' => $lineId,
                'line_phone' => $normalizedLinePhone,
                'contact_id' => $contactId,
                'contact_phone' => $normalizedPhone,
                'contact_name' => $name,
                'status' => ConversationStatus::Open,
                'response_type' => $responseType->value,
                'unread_count' => 0,
                'last_message_at' => now(),
            ]);

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'body' => 'Contact saved',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::System,
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
            ]);

            return $conversation->refresh();
        });
    }

    public function findOrCreateConversation(
        int $lineId,
        ?string $linePhone,
        string $contactPhone,
        ?string $contactName = null,
        ?int $contactId = null,
    ): Conversation {
        $contactPhone = PhoneNormalizer::normalize($contactPhone) ?? $contactPhone;
        $linePhone = PhoneNormalizer::normalize($linePhone) ?? $linePhone;

        return DB::transaction(function () use ($lineId, $linePhone, $contactPhone, $contactName, $contactId): Conversation {
            $conversation = Conversation::query()
                ->where('whatsapp_line_id', $lineId)
                ->where('contact_phone', $contactPhone)
                ->first();

            if ($conversation !== null) {
                if ($contactName && blank($conversation->contact_name)) {
                    $conversation->forceFill(['contact_name' => $contactName])->save();
                }
                return $conversation;
            }

            return Conversation::query()->create([
                'whatsapp_line_id' => $lineId,
                'line_phone' => $linePhone,
                'contact_id' => $contactId,
                'contact_phone' => $contactPhone,
                'contact_name' => $contactName,
                'status' => ConversationStatus::Open,
                'response_type' => 'human_response',
                'unread_count' => 0,
                'last_message_at' => now(),
            ]);
        });
    }
}
