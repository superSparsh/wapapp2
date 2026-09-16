<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\ContactOptInStatus;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\Log;

class StopKeywordService
{
    public function __construct(
        private readonly InboxOutboundService $outboundService,
    ) {}

    /**
     * Handle STOP / START keywords on an inbound message.
     *
     * @return bool True when the keyword was handled (caller should skip chatbot/triggers)
     */
    public function handle(Conversation $conversation, Message $inboundMessage): bool
    {
        $normalized = $this->normalizeInboundText((string) $inboundMessage->body);

        if ($normalized === '') {
            return false;
        }

        if ($this->isStopKeyword($normalized)) {
            $this->applyStop($conversation, $inboundMessage);

            return true;
        }

        if ($this->isStartKeyword($normalized)) {
            $this->applyStart($conversation, $inboundMessage);

            return true;
        }

        return false;
    }

    public function isStopKeyword(string $normalizedText): bool
    {
        $keywords = array_map('strtolower', config('opt_in.stop_keywords', ['stop', 'stop promotions']));

        return in_array($normalizedText, $keywords, true);
    }

    public function isStartKeyword(string $normalizedText): bool
    {
        $keywords = array_map('strtolower', config('opt_in.start_keywords', ['start']));

        return in_array($normalizedText, $keywords, true);
    }

    /**
     * Extract plain / interactive reply text and lowercase it.
     */
    public function normalizeInboundText(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (($raw[0] ?? '') !== '{' && ($raw[0] ?? '') !== '[') {
            return strtolower($raw);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return strtolower($raw);
        }

        if (! empty($decoded['content']) && is_array($decoded['content'])) {
            $c = $decoded['content'];
            foreach ([
                $c['interactive']['button_reply']['title'] ?? null,
                $c['interactive']['list_reply']['title'] ?? null,
                $c['button_reply']['title'] ?? null,
                $c['list_reply']['title'] ?? null,
                $c['text'] ?? null,
            ] as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    return strtolower(trim($candidate));
                }
            }
        }

        foreach ([
            $decoded['interactive']['button_reply']['title'] ?? null,
            $decoded['interactive']['list_reply']['title'] ?? null,
            $decoded['button_reply']['title'] ?? null,
            $decoded['list_reply']['title'] ?? null,
            $decoded['text'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtolower(trim($candidate));
            }
        }

        return strtolower($raw);
    }

    private function applyStop(Conversation $conversation, Message $inboundMessage): void
    {
        $contact = $this->resolveContact($conversation);
        if ($contact instanceof Contact) {
            $alreadyStopped = $contact->status === ContactStatus::Unsubscribed
                || $contact->opt_in_status === ContactOptInStatus::OptedOut;

            $contact->unsubscribe();

            $metadata = $contact->metadata ?? [];
            $metadata['stopped_via_keyword'] = true;
            $metadata['stopped_at'] = now()->toIso8601String();
            $metadata['stopped_message_id'] = $inboundMessage->id;
            $contact->forceFill(['metadata' => $metadata])->save();

            $this->markCampaignRecipientsUnsubscribed($contact->phone);

            if (! $alreadyStopped) {
                $this->sendConfirmation(
                    $conversation,
                    (string) config(
                        'opt_in.stop_confirmation',
                        'Thank you. You have been unsubscribed and will no longer receive promotional messages from us. To start receiving messages again, reply START.'
                    ),
                );
            }
        } else {
            Log::warning('STOP keyword received but no contact found', [
                'conversation_id' => $conversation->id,
                'phone' => $conversation->contact_phone,
            ]);
        }
    }

    private function applyStart(Conversation $conversation, Message $inboundMessage): void
    {
        $contact = $this->resolveContact($conversation);
        if (! $contact instanceof Contact) {
            return;
        }

        $wasStopped = $contact->status === ContactStatus::Unsubscribed
            || $contact->opt_in_status === ContactOptInStatus::OptedOut
            || (bool) data_get($contact->metadata, 'stopped_via_keyword');

        if (! $wasStopped) {
            return;
        }

        $contact->subscribe();

        $metadata = $contact->metadata ?? [];
        unset($metadata['stopped_via_keyword'], $metadata['stopped_at'], $metadata['stopped_message_id']);
        $metadata['restarted_via_keyword'] = true;
        $metadata['restarted_at'] = now()->toIso8601String();
        $metadata['restarted_message_id'] = $inboundMessage->id;
        $contact->forceFill(['metadata' => $metadata])->save();

        $this->sendConfirmation(
            $conversation,
            (string) config(
                'opt_in.start_confirmation',
                'Welcome back! You have been re-subscribed and can receive messages again.'
            ),
        );
    }

    private function resolveContact(Conversation $conversation): ?Contact
    {
        if ($conversation->contact_id) {
            $contact = Contact::query()->find($conversation->contact_id);
            if ($contact instanceof Contact) {
                return $contact;
            }
        }

        $variants = PhoneNormalizer::lookupVariants($conversation->contact_phone);
        if ($variants === []) {
            return null;
        }

        return Contact::query()->whereIn('phone', $variants)->first();
    }

    private function markCampaignRecipientsUnsubscribed(string $phone): void
    {
        $variants = PhoneNormalizer::lookupVariants($phone);
        if ($variants === []) {
            $variants = [$phone];
        }

        CampaignRecipient::query()
            ->whereIn('contact_phone', $variants)
            ->whereIn('status', [
                CampaignRecipientStatus::Pending->value,
                CampaignRecipientStatus::Sent->value,
            ])
            ->update([
                'status' => CampaignRecipientStatus::Unsubscribed->value,
                'unsubscribed_at' => now(),
            ]);
    }

    private function sendConfirmation(Conversation $conversation, string $body): void
    {
        $body = trim($body);
        if ($body === '') {
            return;
        }

        try {
            // STOP/START confirmations must go out even outside the 24h window.
            $this->outboundService->sendText($conversation, $body, enforceWindow: false);
        } catch (\Throwable $e) {
            Log::warning('Failed sending STOP/START confirmation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
