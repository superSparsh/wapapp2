<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Services;

use App\Domains\ThirdParty\Enums\IntegrationStatus;
use App\Domains\ThirdParty\Models\ShopifyIntegration;
use App\Domains\ThirdParty\Models\ShopifySendData;
use App\Support\ListingSort;
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
     * Save the Shopify store domain URL and register central domain lookup.
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

        return $integration->fresh() ?? $integration;
    }

    /**
     * Bulk-save scope flags + templates (legacy webhookdatascopes / webhookdatatemplate shape).
     *
     * @param  array<int, array{key: string, value: string}>  $scopes
     * @param  array<int, array{key: string, value: string|null}>  $templateSelected
     */
    public function saveScopes(
        int $userId,
        array $scopes,
        array $templateSelected,
        ?string $selectedScope = null,
        mixed $mailListId = null,
    ): ShopifyIntegration {
        $integration = $this->findOrCreate($userId);
        $settings = $integration->settings ?? [];

        $settings['access_scope_check'] = array_values($scopes);
        $settings['template_selected'] = array_values($templateSelected);

        if ($selectedScope !== null && $mailListId !== null) {
            $scopeColumn = $this->scopeToColumn($selectedScope);
            if ($scopeColumn !== null) {
                $settings[$scopeColumn] = is_array($mailListId)
                    ? ($mailListId['value'] ?? $mailListId['id'] ?? null)
                    : $mailListId;
            }
        }

        $integration->settings = $settings;
        if ($this->hasAnyEnabledScope($settings['access_scope_check'])) {
            $integration->status = IntegrationStatus::Enabled;
        }
        $integration->save();

        return $integration->fresh() ?? $integration;
    }

    /**
     * Upsert a single webhook scope + optional template / mail list (UI form).
     */
    public function upsertScope(
        int $userId,
        string $scopeKey,
        bool $enabled,
        ?string $templateId = null,
        mixed $mailListId = null,
    ): ShopifyIntegration {
        $integration = $this->findOrCreate($userId);
        $settings = $integration->settings ?? [];

        $scopes = is_array($settings['access_scope_check'] ?? null) ? $settings['access_scope_check'] : [];
        $templates = is_array($settings['template_selected'] ?? null)
            ? $settings['template_selected']
            : (is_array($settings['templateselected'] ?? null) ? $settings['templateselected'] : []);

        $scopes = $this->mergeKeyedList($scopes, $scopeKey, $enabled ? 'yes' : 'no');

        if ($enabled && filled($templateId)) {
            $templates = $this->mergeKeyedList($templates, $scopeKey, (string) $templateId);
        } elseif (! $enabled) {
            $templates = $this->mergeKeyedList($templates, $scopeKey, null);
        }

        $settings['access_scope_check'] = $scopes;
        $settings['template_selected'] = $templates;

        $scopeColumn = $this->scopeToColumn($scopeKey);
        if ($scopeColumn !== null && $mailListId !== null) {
            $settings[$scopeColumn] = $mailListId;
        }

        $integration->settings = $settings;
        if ($this->hasAnyEnabledScope($scopes)) {
            $integration->status = IntegrationStatus::Enabled;
        }
        $integration->save();

        return $integration->fresh() ?? $integration;
    }

    /**
     * Disable / remove a scope from the enabled list.
     */
    public function removeScope(int $userId, string $scopeKey): ShopifyIntegration
    {
        return $this->upsertScope($userId, $scopeKey, false, null, null);
    }

    /**
     * Enabled scopes with labels + template ids for the UI.
     *
     * @return list<array{key: string, label: string, template_id: string|null, mail_list_id: mixed, needs_mail_list: bool}>
     */
    public function enabledScopes(ShopifyIntegration $integration): array
    {
        $settings = is_array($integration->settings) ? $integration->settings : [];
        $scopes = is_array($settings['access_scope_check'] ?? null) ? $settings['access_scope_check'] : [];
        $templates = is_array($settings['template_selected'] ?? null)
            ? $settings['template_selected']
            : (is_array($settings['templateselected'] ?? null) ? $settings['templateselected'] : []);
        $catalog = config('shopify.scopes', []);

        $templateMap = [];
        foreach ($templates as $row) {
            if (is_array($row) && isset($row['key'])) {
                $templateMap[(string) $row['key']] = $row['value'] ?? null;
            }
        }

        $enabled = [];
        foreach ($scopes as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '' || strtolower((string) ($row['value'] ?? '')) !== 'yes') {
                continue;
            }

            $meta = $catalog[$key] ?? ['label' => str_replace('_', ' ', ucwords($key, '_')), 'needs_mail_list' => false];
            $enabled[] = [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'template_id' => isset($templateMap[$key]) && filled($templateMap[$key]) ? (string) $templateMap[$key] : null,
                'mail_list_id' => $settings[$key] ?? null,
                'needs_mail_list' => (bool) ($meta['needs_mail_list'] ?? false),
            ];
        }

        return $enabled;
    }

    /**
     * Get paginated send data for a user.
     */
    public function sendData(
        int $userId,
        int $perPage = 50,
        string $sort = 'created_at',
        string $direction = 'desc',
    ): LengthAwarePaginator {
        $query = ShopifySendData::query()->where('user_id', $userId);

        ListingSort::apply($query, $sort, $direction, [
            'created_at' => 'created_at',
            'sent_at' => 'sent_at',
            'event_type' => 'event_type',
            'status' => 'status',
            'whatsapp_number' => 'whatsapp_number',
        ], 'created_at');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<int, mixed>  $list
     * @return list<array{key: string, value: string|null}>
     */
    private function mergeKeyedList(array $list, string $key, mixed $value): array
    {
        $out = [];
        $found = false;

        foreach ($list as $row) {
            if (! is_array($row) || ! isset($row['key'])) {
                continue;
            }
            if ((string) $row['key'] === $key) {
                $found = true;
                if ($value === null) {
                    continue;
                }
                $out[] = ['key' => $key, 'value' => is_scalar($value) ? (string) $value : null];

                continue;
            }
            $out[] = [
                'key' => (string) $row['key'],
                'value' => isset($row['value']) && is_scalar($row['value']) ? (string) $row['value'] : null,
            ];
        }

        if (! $found && $value !== null) {
            $out[] = ['key' => $key, 'value' => is_scalar($value) ? (string) $value : null];
        }

        return array_values($out);
    }

    /**
     * @param  array<int, mixed>  $scopes
     */
    private function hasAnyEnabledScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (is_array($scope)
                && strtolower((string) ($scope['value'] ?? '')) === 'yes'
                && filled($scope['key'] ?? null)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map a Shopify scope name to the settings column name for product mail lists.
     */
    private function scopeToColumn(string $scope): ?string
    {
        return match ($scope) {
            'product_listings_add' => 'product_listings_add',
            'product_listings_remove' => 'product_listings_remove',
            'product_listings_update' => 'product_listings_update',
            'products_create' => 'products_create',
            'products_delete' => 'products_delete',
            'products_update' => 'products_update',
            default => null,
        };
    }
}
