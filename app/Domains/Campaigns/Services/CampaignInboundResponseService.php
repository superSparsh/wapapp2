<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Enums\CampaignRecipientStatus;
use App\Models\CampaignRecipient;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marks the latest eligible campaign recipient as Response when a contact replies.
 * Dashboard / stats "Response" gauges read CampaignRecipientStatus::Response (+ total_response).
 */
class CampaignInboundResponseService
{
    /**
     * @param  array<string, mixed>  $inboundItem  Raw Alibaba inbound message item (optional context ids)
     */
    public function recordReply(Conversation $conversation, Message $inboundMessage, array $inboundItem = []): void
    {
        try {
            DB::transaction(function () use ($conversation, $inboundMessage, $inboundItem): void {
                $recipient = $this->resolveRecipient($conversation, $inboundItem);

                if ($recipient === null) {
                    return;
                }

                if ($recipient->status === CampaignRecipientStatus::Response) {
                    return;
                }

                $now = $inboundMessage->created_at ?? now();

                $recipient->forceFill([
                    'status' => CampaignRecipientStatus::Response,
                    'responded_at' => $recipient->responded_at ?? $now,
                    'read_at' => $recipient->read_at ?? $now,
                    'delivered_at' => $recipient->delivered_at ?? $recipient->sent_at ?? $now,
                    'sent_at' => $recipient->sent_at ?? $now,
                ])->save();

                $recipient->campaign?->increment('total_response');
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to record campaign inbound response', [
                'conversation_id' => $conversation->id,
                'message_id' => $inboundMessage->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $inboundItem
     */
    private function resolveRecipient(Conversation $conversation, array $inboundItem): ?CampaignRecipient
    {
        $contextMessageId = $this->extractContextMessageId($inboundItem);

        if ($contextMessageId !== null) {
            $byContext = CampaignRecipient::query()
                ->where('message_id', $contextMessageId)
                ->whereIn('status', $this->eligibleStatuses())
                ->orderByDesc('id')
                ->first();

            if ($byContext !== null) {
                return $byContext;
            }

            // Campaigns may store local messages.id; resolve via inbox external id.
            $localMessage = Message::query()
                ->where('external_message_id', $contextMessageId)
                ->first();

            if ($localMessage !== null) {
                $byLocal = CampaignRecipient::query()
                    ->where('message_id', (string) $localMessage->id)
                    ->whereIn('status', $this->eligibleStatuses())
                    ->orderByDesc('id')
                    ->first();

                if ($byLocal !== null) {
                    return $byLocal;
                }
            }
        }

        $variants = PhoneNormalizer::lookupVariants($conversation->contact_phone);
        if ($variants === [] && filled($conversation->contact_phone)) {
            $variants = [(string) $conversation->contact_phone];
        }

        if ($variants === [] && $conversation->contact_id === null) {
            return null;
        }

        $windowDays = 30;

        $query = CampaignRecipient::query()
            ->whereIn('status', $this->eligibleStatuses())
            ->where(function ($q) use ($conversation, $variants): void {
                if ($conversation->contact_id) {
                    $q->where('contact_id', $conversation->contact_id);
                }
                if ($variants !== []) {
                    $q->orWhereIn('contact_phone', $variants);
                }
            })
            ->where(function ($q) use ($windowDays): void {
                $q->where('sent_at', '>=', now()->subDays($windowDays))
                    ->orWhere(function ($inner) use ($windowDays): void {
                        $inner->whereNull('sent_at')
                            ->where('created_at', '>=', now()->subDays($windowDays));
                    });
            })
            ->orderByDesc(DB::raw('COALESCE(sent_at, delivered_at, created_at)'))
            ->orderByDesc('id');

        return $query->first();
    }

    /**
     * @return list<string>
     */
    private function eligibleStatuses(): array
    {
        return [
            CampaignRecipientStatus::Sent->value,
            CampaignRecipientStatus::Delivered->value,
            CampaignRecipientStatus::Read->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $inboundItem
     */
    private function extractContextMessageId(array $inboundItem): ?string
    {
        $candidates = [
            $inboundItem['ContextMessageId'] ?? null,
            $inboundItem['contextMessageId'] ?? null,
            $inboundItem['ReplyToMessageId'] ?? null,
            $inboundItem['replyToMessageId'] ?? null,
            data_get($inboundItem, 'Context.MessageId'),
            data_get($inboundItem, 'context.id'),
            data_get($inboundItem, 'context.message_id'),
        ];

        $message = $inboundItem['Message'] ?? $inboundItem['message'] ?? null;
        if (is_string($message) && ($message[0] ?? '') === '{') {
            $decoded = json_decode($message, true);
            if (is_array($decoded)) {
                $candidates[] = data_get($decoded, 'context.id');
                $candidates[] = data_get($decoded, 'context.message_id');
                $candidates[] = data_get($decoded, 'contextMessageId');
            }
        } elseif (is_array($message)) {
            $candidates[] = data_get($message, 'context.id');
            $candidates[] = data_get($message, 'context.message_id');
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
