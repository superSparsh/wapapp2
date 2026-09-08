<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Services;

use App\Domains\Webhooks\Services\WebhookDeliveryService;
use App\Models\Campaign;
use App\Models\CampaignWebhook;
use Illuminate\Support\Str;

class CampaignWebhookService
{
    public function __construct(
        private readonly WebhookDeliveryService $deliveryService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Campaign $campaign, string $event, array $payload): void
    {
        $hooks = CampaignWebhook::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'active')
            ->get();

        foreach ($hooks as $hook) {
            $events = (array) ($hook->events ?? []);

            if ($events !== [] && ! in_array($event, $events, true)) {
                continue;
            }

            $this->deliveryService->dispatchRaw(
                url: $hook->url,
                secret: (string) ($hook->secret_key ?? ''),
                eventType: $event,
                payload: array_merge(['campaign_id' => $campaign->id, 'event' => $event], $payload),
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Campaign $campaign, array $data): CampaignWebhook
    {
        return CampaignWebhook::query()->create([
            'campaign_id' => $campaign->id,
            'url' => $data['url'],
            'events' => $data['events'] ?? ['sent', 'delivered', 'failed', 'read'],
            'secret_key' => $data['secret_key'] ?? Str::random(32),
            'status' => $data['status'] ?? 'active',
        ]);
    }
}
