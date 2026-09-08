<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CampaignWebhookService
{
    /**
     * @param array<string, mixed> $payload
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

            try {
                $body = array_merge(['campaign_id' => $campaign->id, 'event' => $event], $payload);
                $signature = hash_hmac('sha256', json_encode($body), (string) ($hook->secret_key ?? ''));

                Http::timeout(5)
                    ->withHeaders([
                        'X-Campaign-Webhook-Signature' => $signature,
                        'X-Campaign-Webhook-Event' => $event,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($hook->url, $body);
            } catch (Throwable $e) {
                Log::warning('Campaign webhook delivery failed', [
                    'url' => $hook->url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
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
