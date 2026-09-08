<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookSubscriptionStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class WebhookSubscriptionService
{
    public function __construct(
        private readonly WebhookDeliveryService $deliveryService,
    ) {}
    /**
     * Paginated subscriptions with delivery counts.
     */
    public function index(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        return WebhookSubscription::query()
            ->when($search, fn ($q) => $q->where('description', 'LIKE', "%{$search}%")->orWhere('url', 'LIKE', "%{$search}%"))
            ->withCount([
                'deliveries',
                'deliveries as sent_count' => fn ($q) => $q->where('status', WebhookDeliveryStatus::Sent),
                'deliveries as failed_count' => fn ($q) => $q->where('status', WebhookDeliveryStatus::Failed),
            ])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Create a subscription with auto-generated secret key.
     */
    public function store(array $data): WebhookSubscription
    {
        $data['secret_key'] = Str::random(32);

        return WebhookSubscription::query()->create($data);
    }

    /**
     * Update a subscription.
     */
    public function update(WebhookSubscription $subscription, array $data): WebhookSubscription
    {
        $subscription->update($data);

        return $subscription->fresh();
    }

    /**
     * Delete a subscription (cascade deletes deliveries via FK).
     */
    public function destroy(WebhookSubscription $subscription): void
    {
        $subscription->delete();
    }

    /**
     * Toggle Active <-> Inactive.
     */
    public function toggleStatus(WebhookSubscription $subscription): WebhookSubscription
    {
        $newStatus = $subscription->status === WebhookSubscriptionStatus::Active
            ? WebhookSubscriptionStatus::Inactive
            : WebhookSubscriptionStatus::Active;

        $subscription->update(['status' => $newStatus]);

        return $subscription->fresh();
    }

    /**
     * Regenerate the secret key and return it.
     */
    public function regenerateSecret(WebhookSubscription $subscription): string
    {
        $newKey = Str::random(32);
        $subscription->update(['secret_key' => $newKey]);

        return $newKey;
    }

    /**
     * Send a test payload to the subscription URL and record the delivery.
     */
    public function testDelivery(WebhookSubscription $subscription): WebhookDelivery
    {
        return $this->deliveryService->dispatch($subscription, 'new_lead', $this->buildTestPayload());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTestPayload(): array
    {
        return [
            'event' => 'new_lead',
            'data' => [
                'id' => (string) now()->timestamp,
                'name' => 'Test User',
                'phone' => '919999999999',
                'message' => 'This is a test webhook payload',
                'created_at' => now()->toIso8601String(),
            ],
            'timestamp' => now()->timestamp,
        ];
    }
}
