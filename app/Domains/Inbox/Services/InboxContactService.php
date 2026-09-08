<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Enums\ConversationResponseType;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class InboxContactService
{
    public function __construct(
        private readonly InboxConversationService $conversationService,
        private readonly InboxBroadcastService $broadcastService,
    ) {}

    public function addContact(
        WhatsappLine $line,
        string $name,
        string $phone,
        ConversationResponseType $responseType = ConversationResponseType::Human,
    ): Conversation {
        $normalizedPhone = PhoneNormalizer::normalize($phone) ?? preg_replace('/\D+/', '', $phone);

        abort_if($normalizedPhone === '' || $normalizedPhone === null, 422, 'A valid phone number is required.');

        $existing = Conversation::query()
            ->where('whatsapp_line_id', $line->id)
            ->where('contact_phone', $normalizedPhone)
            ->first();

        abort_if($existing !== null, 422, 'Contact already exists.');

        return DB::transaction(function () use ($line, $name, $normalizedPhone, $responseType): Conversation {
            $conversation = $this->conversationService->findOrCreateConversation(
                line: $line,
                contactPhone: $normalizedPhone,
                contactName: $name,
            );

            $conversation->forceFill([
                'contact_name' => $name,
                'response_type' => $responseType->value,
                'last_message_at' => now(),
            ])->save();

            Contact::query()
                ->whereKey($conversation->contact_id)
                ->update(['name' => $name]);

            Message::query()->create([
                'conversation_id' => $conversation->id,
                'body' => 'Contact saved',
                'direction' => MessageDirection::Outbound,
                'message_type' => MessageType::System,
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
            ]);

            $conversation = $conversation->refresh();
            $this->broadcastService->threadUpdated($conversation);

            return $conversation;
        });
    }
}
