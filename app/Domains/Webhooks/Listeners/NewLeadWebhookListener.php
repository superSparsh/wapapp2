<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Listeners;

use App\Domains\Audience\Enums\ContactStatus;
use App\Domains\Webhooks\Jobs\DispatchOutboundWebhookJob;
use App\Domains\Webhooks\Services\NewLeadWebhookPayloadBuilder;
use App\Enums\ContactOptInStatus;
use App\Enums\MessageDirection;
use App\Enums\WebhookSubscriptionStatus;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\MailList;
use App\Models\Message;
use App\Models\WebhookSubscription;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Log;

class NewLeadWebhookListener
{
    public function __construct(
        private readonly NewLeadWebhookPayloadBuilder $payloadBuilder,
    ) {}

    /**
     * Dispatch outbound webhooks for active new_lead subscriptions (legacy parity).
     *
     * Fires only on the first inbound message for a contact↔line conversation.
     */
    public function handle(Message $message, Conversation $conversation): void
    {
        if (! $this->isFirstInboundForConversation($message, $conversation)) {
            return;
        }

        $line = $conversation->whatsappLine
            ?? WhatsappLine::query()->find($conversation->whatsapp_line_id);

        if (! $line instanceof WhatsappLine) {
            return;
        }

        $subscriptions = $this->subscriptionsForLine((int) $line->id);

        if ($subscriptions->isEmpty()) {
            return;
        }

        $contact = $conversation->contact;
        $from = (string) ($conversation->contact_phone ?? $contact?->phone ?? '');
        $to = (string) ($conversation->line_phone ?? $line->phone ?? '');
        $name = (string) ($contact?->name ?? $conversation->contact_name ?? 'Unknown');
        $body = (string) $message->body;

        $payload = $this->payloadBuilder->build(
            from: $from,
            to: $to,
            name: $name,
            message: $body,
            messageId: $message->external_message_id ?: $message->id,
            originalTimestamp: ($message->created_at?->timestamp ?? now()->timestamp) * 1000,
            line: $line,
        );

        foreach ($subscriptions as $subscription) {
            $this->attachToAudienceList($subscription, $payload['data']);

            DispatchOutboundWebhookJob::dispatch(
                subscriptionId: (int) $subscription->id,
                eventType: 'new_lead',
                payload: $payload,
            )->onQueue(config('webhooks.queue', 'default'));
        }
    }

    private function isFirstInboundForConversation(Message $message, Conversation $conversation): bool
    {
        return ! Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Inbound)
            ->whereKeyNot($message->id)
            ->exists();
    }

    /**
     * Active new_lead webhooks for one business line.
     * Null whatsapp_line_id only matches the account default line (legacy null new_contact_id).
     *
     * @return \Illuminate\Support\Collection<int, WebhookSubscription>
     */
    private function subscriptionsForLine(int $lineId)
    {
        $defaultLineId = WhatsappLine::query()
            ->where('is_default', true)
            ->value('id')
            ?? WhatsappLine::query()->orderBy('id')->value('id');

        return WebhookSubscription::query()
            ->where('status', WebhookSubscriptionStatus::Active)
            ->whereJsonContains('events', 'new_lead')
            ->where(function ($query) use ($lineId, $defaultLineId): void {
                $query->where('whatsapp_line_id', $lineId);
                if ($defaultLineId !== null && (int) $defaultLineId === $lineId) {
                    $query->orWhereNull('whatsapp_line_id');
                }
            })
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachToAudienceList(WebhookSubscription $subscription, array $data): void
    {
        if (blank($subscription->audience_list_id)) {
            return;
        }

        $list = MailList::query()->find($subscription->audience_list_id);
        if (! $list instanceof MailList) {
            return;
        }

        $phoneE164 = trim((string) ($data['phone_e164'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $normalized = $phoneE164 !== '' ? $phoneE164 : $phone;

        if ($normalized === '') {
            return;
        }

        try {
            Contact::query()->firstOrCreate(
                [
                    'mail_list_id' => $list->id,
                    'phone' => $normalized,
                ],
                [
                    'name' => (string) ($data['name'] ?? $normalized),
                    'country_code' => isset($data['country_code'])
                        ? ltrim((string) $data['country_code'], '+')
                        : null,
                    'status' => ContactStatus::Subscribed,
                    'opt_in_status' => ContactOptInStatus::OptedIn,
                    'opted_in_at' => now(),
                    'send_opt_in_message' => 'no',
                ],
            );
        } catch (\Throwable $e) {
            Log::error('Failed to add contact to audience list from webhook', [
                'audience_list_id' => $subscription->audience_list_id,
                'phone' => $normalized,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
