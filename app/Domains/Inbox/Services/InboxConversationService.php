<?php

declare(strict_types=1);

namespace App\Domains\Inbox\Services;

use App\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class InboxConversationService
{
    public function findOrCreateConversation(
        WhatsappLine $line,
        string $contactPhone,
        ?string $contactName = null,
    ): Conversation {
        $contactPhone = PhoneNormalizer::normalize($contactPhone) ?? $contactPhone;
        $linePhone = PhoneNormalizer::normalize($line->phone) ?? $line->phone;

        return DB::transaction(function () use ($line, $contactPhone, $linePhone, $contactName): Conversation {
            $contact = Contact::query()->firstOrCreate(
                ['phone' => $contactPhone],
                ['name' => $contactName],
            );

            if ($contactName && blank($contact->name)) {
                $contact->forceFill(['name' => $contactName])->save();
            }

            return Conversation::query()->firstOrCreate(
                [
                    'whatsapp_line_id' => $line->id,
                    'contact_phone' => $contactPhone,
                ],
                [
                    'contact_id' => $contact->id,
                    'line_phone' => $linePhone,
                    'contact_name' => $contactName ?: $contact->name,
                    'status' => ConversationStatus::Open,
                    'response_type' => 'human_response',
                    'unread_count' => 0,
                    'last_message_at' => now(),
                ],
            );
        });
    }
}
