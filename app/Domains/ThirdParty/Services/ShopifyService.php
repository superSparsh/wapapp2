<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Models\ShopifyIntegration;
use App\Domains\ThirdParty\Models\ShopifySendData;
use App\Domains\ThirdParty\Services\ShopifyWebhookIngestService;
use Illuminate\Pagination\LengthAwarePaginator;

class ShopifyService
{
    public function __construct(
        private readonly ShopifyWebhookIngestService $webhookIngest,
    ) {}

    /**
     * Get or create the Shopify integration for a user.
     */
    public function findOrCreate(int $userId): ShopifyIntegration
    {
        /** @var ShopifyIntegration */
        return ShopifyIntegration::query()->firstOrCreate(
            ['user_id' => $userId],
            ['status' => IntegrationStatus::Disabled, 'settings' => []]
        );
    }

    /**
     * Get the integration for a given user.
     */
    public function find(int $userId): ?ShopifyIntegration
    {
        /** @var ShopifyIntegration|null */
        return ShopifyIntegration::query()->where('user_id', $userId)->first();
    }

    /**
     * Save the Shopify store domain URL.
     */
    public function saveDomainUrl(int $userId, string $url): ShopifyIntegration
    {
        $integration = $this->findOrCreate($userId);
        $settings = $integration->settings ?? [];
        $settings['shopifydomainurl'] = $url;
        $integration->settings = $settings;
        $integration->save();

        $tenantId = (string) (tenant('id') ?? '');
        if ($tenantId !== '') {
            $this->webhookIngest->registerDomain($tenantId, $url, $userId);
        }

        return $integration;
    }

    /**
     * Save scope settings and optional template/maillist association for a scope.
     *
     * @param array<int, array{key: string, value: string}> $scopes
     * @param array<int, array{key: string, value: string|null}> $templateSelected
     */
    public function saveScopes(
        int $userId,
        array $scopes,
        array $templateSelected,
        ?string $selectedScope,
        mixed $mailListId,
    ): void {
        $integration = $this->findOrCreate($userId);
        $settings = $integration->settings ?? [];

        $settings['access_scope_check'] = $scopes;
        $settings['template_selected']  = $templateSelected;

        // Map scope → maillist_id
        if ($selectedScope !== null && $mailListId !== null) {
            $scopeColumn = $this->scopeToColumn($selectedScope);
            if ($scopeColumn !== null) {
                $settings[$scopeColumn] = $mailListId;
            }
        }

        $integration->settings = $settings;
        $integration->status   = IntegrationStatus::Enabled;
        $integration->save();
    }

    /**
     * Get paginated send data for a user.
     */
    public function sendData(int $userId, int $perPage = 50): LengthAwarePaginator
    {
        return ShopifySendData::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Map a Shopify scope name to the settings column name.
     */
    private function scopeToColumn(string $scope): ?string
    {
        return match ($scope) {
            'product_listings_add'    => 'product_listings_add',
            'product_listings_remove' => 'product_listings_remove',
            'product_listings_update' => 'product_listings_update',
            'products_create'         => 'products_create',
            'products_delete'         => 'products_delete',
            'products_update'         => 'products_update',
            default                   => null,
        };
    }
}
