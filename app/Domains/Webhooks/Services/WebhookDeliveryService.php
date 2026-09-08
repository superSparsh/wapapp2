<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Services;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebhookDeliveryService
{
    /**
     * Send a one-off webhook to an arbitrary URL (e.g. campaign-specific hooks).
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatchRaw(string $url, string $secret, string $eventType, array $payload): void
    {
        $jsonBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $jsonBody, $secret);

        Http::timeout(config('webhooks.timeout', 10))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $eventType,
            ])
            ->post($url, $payload);
    }

    /**
     * Send a webhook payload to a subscription URL and record the delivery.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(WebhookSubscription $subscription, string $eventType, array $payload): WebhookDelivery
    {
        $jsonBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $jsonBody, $subscription->secret_key);
        $deliveryUuid = (string) Str::uuid();

        $start = microtime(true);

        try {
            $response = Http::timeout(config('webhooks.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $eventType,
                    'X-Webhook-Delivery' => $deliveryUuid,
                ])
                ->post($subscription->url, $payload);

            $duration = (int) ((microtime(true) - $start) * 1000);
            $status = $response->successful() ? WebhookDeliveryStatus::Sent : WebhookDeliveryStatus::Failed;

            $subscription->update(['last_triggered_at' => now()]);

            return WebhookDelivery::query()->create([
                'webhook_subscription_id' => $subscription->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 5000),
                'error_message' => $response->successful() ? null : 'HTTP '.$response->status(),
                'status' => $status,
                'attempt_count' => 1,
                'duration_ms' => $duration,
                'sent_at' => now(),
                'response_received_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);

            return WebhookDelivery::query()->create([
                'webhook_subscription_id' => $subscription->id,
                'event_type' => $eventType,
                'payload' => $payload,
                'error_message' => $e->getMessage(),
                'status' => WebhookDeliveryStatus::Failed,
                'attempt_count' => 1,
                'duration_ms' => $duration,
                'sent_at' => now(),
                'next_retry_at' => now()->addMinute(),
            ]);
        }
    }

    /**
     * Retry a failed delivery.
     */
    public function retry(WebhookDelivery $delivery): WebhookDelivery
    {
        $subscription = $delivery->subscription;

        if (! $subscription) {
            return $delivery;
        }

        $jsonBody = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $jsonBody, $subscription->secret_key);

        $start = microtime(true);

        try {
            $response = Http::timeout(config('webhooks.timeout', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $delivery->event_type,
                    'X-Webhook-Delivery' => $delivery->uuid ?? (string) Str::uuid(),
                ])
                ->post($subscription->url, $delivery->payload);

            $duration = (int) ((microtime(true) - $start) * 1000);
            $status = $response->successful() ? WebhookDeliveryStatus::Sent : WebhookDeliveryStatus::Failed;

            $delivery->update([
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 5000),
                'error_message' => $response->successful() ? null : 'HTTP '.$response->status(),
                'status' => $status,
                'attempt_count' => $delivery->attempt_count + 1,
                'duration_ms' => $duration,
                'response_received_at' => now(),
                'next_retry_at' => null,
            ]);
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);

            $delivery->update([
                'error_message' => $e->getMessage(),
                'status' => WebhookDeliveryStatus::Failed,
                'attempt_count' => $delivery->attempt_count + 1,
                'duration_ms' => $duration,
            ]);
        }

        return $delivery->fresh();
    }

    /**
     * Aggregated delivery metrics (single query, no N+1).
     *
     * @return array{total: int, successful: int, pending: int, failed: int}
     */
    public function metrics(?int $subscriptionId = null): array
    {
        $query = WebhookDelivery::query()->when($subscriptionId, fn ($q) => $q->where('webhook_subscription_id', $subscriptionId));

        $result = $query->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        ")->first();

        return [
            'total' => (int) ($result->total ?? 0),
            'successful' => (int) ($result->successful ?? 0),
            'pending' => (int) ($result->pending ?? 0),
            'failed' => (int) ($result->failed ?? 0),
        ];
    }

    /**
     * Paginated delivery logs with filters.
     */
    public function logs(Request $request, int $perPage = 15, ?int $subscriptionId = null): LengthAwarePaginator
    {
        return WebhookDelivery::query()
            ->with('subscription:id,description,url')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($subscriptionId !== null, fn ($q) => $q->where('webhook_subscription_id', $subscriptionId))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->input('search');
                $q->where(function ($sub) use ($search): void {
                    $sub->where('event_type', 'LIKE', "%{$search}%")
                        ->orWhere('payload', 'LIKE', "%{$search}%")
                        ->orWhere('error_message', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->input('date_to').' 23:59:59'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Single delivery detail.
     */
    public function show(WebhookDelivery $delivery): WebhookDelivery
    {
        $delivery->load('subscription:id,description,url,secret_key');

        return $delivery;
    }

    /**
     * Delete a delivery log.
     */
    public function destroy(WebhookDelivery $delivery): void
    {
        $delivery->forceDelete();
    }
}
