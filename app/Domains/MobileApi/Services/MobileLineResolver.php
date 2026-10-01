<?php

declare(strict_types=1);

namespace App\Domains\MobileApi\Services;

use App\Domains\Inbox\Services\InboxAccessService;
use App\Domains\Inbox\Services\InboxConversationService;
use App\Domains\MobileApi\Support\MobileInboxPresenter;
use App\Models\Conversation;
use App\Models\WhatsappLine;
use App\Support\PhoneNormalizer;

final class MobileLineResolver
{
    public function __construct(
        private readonly InboxAccessService $accessService,
    ) {}

    public function resolve(?string $whatsappNumber = null, ?string $lineUuid = null): WhatsappLine
    {
        $query = WhatsappLine::query()->orderByDesc('is_default')->orderBy('id');

        $assigned = $this->accessService->assignedLineIds();
        if ($assigned !== []) {
            $query->whereIn('id', $assigned);
        }

        if (filled($lineUuid)) {
            $line = (clone $query)->where('uuid', $lineUuid)->first();
            if ($line instanceof WhatsappLine) {
                return $line;
            }
        }

        if (filled($whatsappNumber)) {
            $normalized = MobileInboxPresenter::normalizePhone($whatsappNumber);
            $variants = array_values(array_unique(array_filter([
                $whatsappNumber,
                $normalized,
                PhoneNormalizer::normalize($whatsappNumber),
            ])));

            $line = (clone $query)->whereIn('phone', $variants)->first();
            if ($line instanceof WhatsappLine) {
                return $line;
            }
        }

        $line = $query->first();
        abort_if($line === null, 422, 'No WhatsApp number is configured for this account.');

        return $line;
    }

    public function findConversation(WhatsappLine $line, ?string $customerPhone = null, ?int $conversationId = null): Conversation
    {
        if ($conversationId !== null && $conversationId > 0) {
            $conversation = Conversation::query()
                ->where('whatsapp_line_id', $line->id)
                ->whereKey($conversationId)
                ->first();
            abort_if($conversation === null, 404, 'Conversation not found.');
            $this->accessService->assertCanAccessConversation($conversation);

            return $conversation;
        }

        abort_if($customerPhone === null || $customerPhone === '', 422, 'customer_phone or conversation id is required.');

        $normalized = MobileInboxPresenter::normalizePhone($customerPhone);
        abort_if($normalized === null || $normalized === '', 422, 'A valid customer phone is required.');

        $conversation = Conversation::query()
            ->where('whatsapp_line_id', $line->id)
            ->where('contact_phone', $normalized)
            ->first();

        abort_if($conversation === null, 404, 'Conversation not found.');
        $this->accessService->assertCanAccessConversation($conversation);

        return $conversation;
    }

    public function findOrCreateConversation(
        InboxConversationService $conversations,
        WhatsappLine $line,
        string $customerPhone,
        ?string $customerName = null,
    ): Conversation {
        $normalized = MobileInboxPresenter::normalizePhone($customerPhone);
        abort_if($normalized === null || $normalized === '', 422, 'A valid to_number is required.');

        $conversation = $conversations->findOrCreateConversation($line, $normalized, $customerName);
        $this->accessService->assertCanAccessConversation($conversation);

        return $conversation;
    }
}
