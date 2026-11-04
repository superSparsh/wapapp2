<?php

declare(strict_types=1);

namespace App\Domains\Audience\Services;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Inbox\Services\InboxBroadcastService;
use App\Domains\Inbox\Services\InboxOutboundService;
use App\Enums\CampaignRecipientStatus;
use App\Enums\ContactOptInStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Collection;
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
        $contacts = $this->resolveContacts($conversation);
        if ($contacts->isEmpty()) {
            Log::warning('STOP keyword received but no contact found', [
                'conversation_id' => $conversation->id,
                'phone' => $conversation->contact_phone,
            ]);

            return;
        }

        $alreadyAllStopped = $contacts->every(fn (Contact $contact): bool => $this->contactIsStopped($contact));

        foreach ($contacts as $contact) {
            $contact->unsubscribe();

            $metadata = $contact->metadata ?? [];
            $metadata['stopped_via_keyword'] = true;
            $metadata['stopped_at'] = now()->toIso8601String();
            $metadata['stopped_message_id'] = $inboundMessage->id;
            $contact->forceFill(['metadata' => $metadata])->save();
        }

        $this->markCampaignRecipientsUnsubscribed((string) $conversation->contact_phone);

        // Drop stale contact so inbox broadcasts pick up STOP immediately.
        $conversation->unsetRelation('contact');
        $this->broadcastThreadUpdated($conversation);

        if (! $alreadyAllStopped) {
            $this->sendConfirmation(
                $conversation,
                (string) config(
                    'opt_in.stop_confirmation',
                    'Thank you. You have been unsubscribed and will no longer receive promotional messages from us. To start receiving messages again, reply START.'
                ),
            );
        }
    }

    private function applyStart(Conversation $conversation, Message $inboundMessage): void
    {
        $contacts = $this->resolveContacts($conversation);
        if ($contacts->isEmpty()) {
            return;
        }

        // Inbox/outbound treat ANY same-phone contact as stopped — clear every match.
        $stoppedContacts = $contacts->filter(fn (Contact $contact): bool => $this->contactIsStopped($contact));
        if ($stoppedContacts->isEmpty()) {
            return;
        }

        foreach ($stoppedContacts as $contact) {
            $contact->subscribe();

            $metadata = $contact->metadata ?? [];
            unset($metadata['stopped_via_keyword'], $metadata['stopped_at'], $metadata['stopped_message_id']);
            $metadata['restarted_via_keyword'] = true;
            $metadata['restarted_at'] = now()->toIso8601String();
            $metadata['restarted_message_id'] = $inboundMessage->id;
            $contact->forceFill(['metadata' => $metadata])->save();
        }

        // Drop stale contact so inbox broadcasts pick up START immediately.
        $conversation->unsetRelation('contact');
        $this->broadcastThreadUpdated($conversation);

        $this->sendConfirmation(
            $conversation,
            (string) config(
                'opt_in.start_confirmation',
                'Welcome back! You have been re-subscribed and can receive messages again.'
            ),
        );
    }

    private function contactIsStopped(Contact $contact): bool
    {
        return $contact->status === ContactStatus::Unsubscribed
            || $contact->opt_in_status === ContactOptInStatus::OptedOut
            || (bool) data_get($contact->metadata, 'stopped_via_keyword');
    }

    private function broadcastThreadUpdated(Conversation $conversation): void
    {
        try {
            app(InboxBroadcastService::class)->threadUpdated($conversation->refresh());
        } catch (\Throwable $e) {
            Log::warning('Failed broadcasting STOP/START thread update', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * All audience rows for this conversation phone (across mail lists).
     * Inbox stopped checks scan every same-phone contact — START/STOP must too.
     *
     * @return Collection<int, Contact>
     */
    private function resolveContacts(Conversation $conversation): Collection
    {
        $variants = PhoneNormalizer::lookupVariants($conversation->contact_phone);
        $contacts = collect();

        if ($variants !== []) {
            $contacts = Contact::query()->whereIn('phone', $variants)->get();
        }

        if ($conversation->contact_id) {
            $linked = Contact::query()->find($conversation->contact_id);
            if ($linked instanceof Contact && ! $contacts->contains('id', $linked->id)) {
                $contacts->push($linked);
            }
        }

        return $contacts->unique('id')->values();
    }

    private function markCampaignRecipientsUnsubscribed(string $phone): void
    {
        $variants = PhoneNormalizer::lookupVariants($phone);
        if ($variants === []) {
            $variants = [$phone];
        }

        $recipients = CampaignRecipient::query()
            ->whereIn('contact_phone', $variants)
            ->whereIn('status', [
                CampaignRecipientStatus::Pending->value,
                CampaignRecipientStatus::Sent->value,
            ])
            ->get(['id', 'campaign_id']);

        if ($recipients->isEmpty()) {
            return;
        }

        CampaignRecipient::query()
            ->whereIn('id', $recipients->pluck('id'))
            ->update([
                'status' => CampaignRecipientStatus::Unsubscribed->value,
                'unsubscribed_at' => now(),
            ]);

        foreach ($recipients->groupBy('campaign_id') as $campaignId => $rows) {
            Campaign::query()
                ->whereKey($campaignId)
                ->increment('total_unsubscribed', $rows->count());
        }
    }

    private function sendConfirmation(Conversation $conversation, string $body): void
    {
        $body = trim($body);
        if ($body === '') {
            return;
        }

        try {
            // STOP/START confirmations must go out even outside the 24h window.
            $this->outboundService->sendText($conversation, $body, enforceWindow: false, allowStopped: true);
        } catch (\Throwable $e) {
            Log::warning('Failed sending STOP/START confirmation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
