<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Listeners;

use App\Domains\Webhooks\Jobs\DispatchOutboundWebhookJob;
use App\Enums\WebhookSubscriptionStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookSubscription;

class NewLeadWebhookListener
{
    /**
     * Dispatch outbound webhooks for all active subscriptions listening to new_lead.
     */
    public function handle(Message $message, Conversation $conversation): void
    {
        $subscriptions = WebhookSubscription::query()
            ->where('status', WebhookSubscriptionStatus::Active)
            ->whereJsonContains('events', 'new_lead')
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $contact = $conversation->contact;

        $payload = [
            'event' => 'new_lead',
            'data' => [
                'id' => (string) $message->id,
                'name' => $contact?->name ?? $conversation->contact_name ?? 'Unknown',
                'phone' => $conversation->contact_phone ?? '',
                'message' => (string) $message->body,
                'created_at' => $message->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'original_timestamp' => (int) ($message->created_at?->timestamp ?? now()->timestamp) * 1000,
            ],
            'timestamp' => $message->created_at?->timestamp ?? now()->timestamp,
        ];

        foreach ($subscriptions as $subscription) {
            DispatchOutboundWebhookJob::dispatch(
                subscriptionId: (int) $subscription->id,
                eventType: 'new_lead',
                payload: $payload,
            )->onQueue(config('webhooks.queue', 'default'));
        }
    }
}
