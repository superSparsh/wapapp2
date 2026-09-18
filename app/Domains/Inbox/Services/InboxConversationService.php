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
        $normalizedPhone = PhoneNormalizer::normalize($contactPhone) ?? $contactPhone;
        $phoneVariants = PhoneNormalizer::lookupVariants($contactPhone);
        if ($phoneVariants === []) {
            $phoneVariants = [$normalizedPhone];
        }
        $linePhone = PhoneNormalizer::normalize($line->phone) ?? $line->phone;

        return DB::transaction(function () use ($line, $normalizedPhone, $phoneVariants, $linePhone, $contactName): Conversation {
            $contact = Contact::query()
                ->whereIn('phone', $phoneVariants)
                ->orderByDesc('id')
                ->first();

            if ($contact === null) {
                $contact = Contact::query()->create([
                    'phone' => $normalizedPhone,
                    'name' => $contactName ?: $normalizedPhone,
                ]);
            } elseif ($contactName && (blank($contact->name) || in_array($contact->name, $phoneVariants, true))) {
                $contact->forceFill(['name' => $contactName])->save();
            }

            $conversation = Conversation::query()
                ->where('whatsapp_line_id', $line->id)
                ->whereIn('contact_phone', $phoneVariants)
                ->withCount('messages')
                ->orderByDesc('messages_count')
                ->orderBy('id')
                ->first();

            if ($conversation === null) {
                $conversation = Conversation::query()->create([
                    'whatsapp_line_id' => $line->id,
                    'contact_phone' => $normalizedPhone,
                    'contact_id' => $contact->id,
                    'line_phone' => $linePhone,
                    'contact_name' => $contactName ?: $contact->name ?: $normalizedPhone,
                    'status' => ConversationStatus::Open,
                    'response_type' => 'human_response',
                    'unread_count' => 0,
                    'last_message_at' => now(),
                ]);
            } else {
                $updates = [];
                if ((int) $conversation->contact_id !== (int) $contact->id) {
                    $updates['contact_id'] = $contact->id;
                }
                if ($conversation->contact_phone !== $normalizedPhone) {
                    $phoneTaken = Conversation::query()
                        ->where('whatsapp_line_id', $line->id)
                        ->where('contact_phone', $normalizedPhone)
                        ->whereKeyNot($conversation->id)
                        ->exists();

                    if (! $phoneTaken) {
                        $updates['contact_phone'] = $normalizedPhone;
                    }
                }
                if ($contactName && (blank($conversation->contact_name) || in_array($conversation->contact_name, $phoneVariants, true))) {
                    $updates['contact_name'] = $contactName;
                } elseif (blank($conversation->contact_name)) {
                    $updates['contact_name'] = $normalizedPhone;
                }

                if ($updates !== []) {
                    $conversation->forceFill($updates)->save();
                }
            }

            return $conversation;
        });
    }
}
