<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Models\ShopifyDomainRegistry;
use App\Models\ShopifyWebhookEvent;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookIngestService
{
    public function verifyHmac(Request $request): bool
    {
        $secret = (string) (config('services.shopify.webhook_secret') ?: config('services.shopify.api_secret') ?: '');
        if ($secret === '') {
            Log::warning('Shopify webhook secret not configured.');

            return false;
        }

        $hmac = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        if ($hmac === '') {
            return false;
        }

        $computed = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($computed, $hmac);
    }

    public function normalizeShopDomain(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');

        return $domain;
    }

    public function resolveTenantId(string $shopDomain): ?string
    {
        $normalized = $this->normalizeShopDomain($shopDomain);
        if ($normalized === '') {
            return null;
        }

        $entry = ShopifyDomainRegistry::query()
            ->where('shop_domain', $normalized)
            ->orWhere('shop_domain', 'https://'.$normalized)
            ->orWhere('shop_domain', 'http://'.$normalized)
            ->first();

        return $entry?->tenant_id;
    }

    public function registerDomain(string $tenantId, string $domainUrl, ?int $userId = null): void
    {
        $normalized = $this->normalizeShopDomain($domainUrl);
        if ($normalized === '' || $tenantId === '') {
            return;
        }

        ShopifyDomainRegistry::query()->updateOrCreate(
            ['shop_domain' => $normalized],
            [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function enqueue(string $tenantId, string $shopDomain, string $topic, array $payload, array $headers = []): ShopifyWebhookEvent
    {
        $payload['shop_domain'] = $this->normalizeShopDomain($shopDomain);

        return ShopifyWebhookEvent::query()->create([
            'tenant_id' => $tenantId,
            'shop_domain' => $payload['shop_domain'],
            'topic' => $topic,
            'payload' => $payload,
            'headers' => $headers,
            'status' => 'pending',
        ]);
    }

    public function topicFromPath(string $path): string
    {
        $path = trim($path, '/');
        $path = preg_replace('#^api/v1/webhooks/shopify/#', '', $path) ?? $path;
        $path = preg_replace('#^webhooks/shopify/#', '', $path) ?? $path;

        return str_replace('/', '_', $path);
    }
}
