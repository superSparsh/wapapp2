<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Services;

use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Models\WhatsappLine;
use App\Support\TenantSafeCache;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Fetches Facebook catalogs and products via Alibaba CAMS API.
 * Aligned with legacy Acelle CatalogService (ListProductCatalog / ListProduct).
 *
 * Results are cached per-line on success only (failures are not cached).
 */
class CatalogService
{
    private const CATALOG_CACHE_TTL = 300; // 5 minutes
    private const PRODUCT_CACHE_TTL = 300; // 5 minutes

    /** Same field set as legacy ListProductCatalogRequest. */
    private const CATALOG_FIELDS = 'id,business,catalog_store,commerce_merchant_settings,default_image_url,fallback_image_url,feed_count,is_catalog_segment,name,product_count,store_catalog_settings,vertical';

    /** Same field set as legacy ListProductRequest. */
    private const PRODUCT_FIELDS = 'id,retailer_id,name,description,brand,price,condition,availability,image_url,inventory';

    private const LIST_LIMIT = 1000;

    private ?string $lastError = null;

    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /**
     * Returns catalogs for the given WhatsApp line.
     *
     * Required CAMS params (legacy): BusinessId, CustSpaceId, Fields, Limit.
     *
     * @return array{success: bool, catalogs?: list<array<string,mixed>>, message?: string}
     */
    public function getCatalogs(WhatsappLine $line): array
    {
        if (! $this->camsClient->isConfigured()) {
            return ['success' => false, 'message' => 'WhatsApp API is not configured.'];
        }

        if (blank($line->alibaba_cust_space_id)) {
            return ['success' => false, 'message' => 'WhatsApp line has no CAMS space ID configured.'];
        }

        $cacheKey = "commerce.catalogs.{$line->id}";
        $cached = TenantSafeCache::get($cacheKey);
        if (is_array($cached)) {
            return ['success' => true, 'catalogs' => $cached];
        }

        $catalogs = $this->fetchCatalogs($line);

        if ($catalogs === null) {
            return [
                'success' => false,
                'message' => $this->lastError ?? 'Failed to retrieve catalogs from API.',
            ];
        }

        TenantSafeCache::put($cacheKey, $catalogs, self::CATALOG_CACHE_TTL);

        return ['success' => true, 'catalogs' => $catalogs];
    }

    /**
     * Returns products for a given catalog.
     *
     * Required CAMS params (legacy): CatalogId, CustSpaceId, Fields, Limit, WabaId.
     *
     * @return array{success: bool, products?: list<array<string,mixed>>, message?: string}
     */
    public function getProducts(WhatsappLine $line, string $catalogId): array
    {
        if (! $this->camsClient->isConfigured()) {
            return ['success' => false, 'message' => 'WhatsApp API is not configured.'];
        }

        if (blank($line->alibaba_cust_space_id)) {
            return ['success' => false, 'message' => 'WhatsApp line has no CAMS space ID configured.'];
        }

        $cacheKey = "commerce.products.{$line->id}.{$catalogId}";
        $cached = TenantSafeCache::get($cacheKey);
        if (is_array($cached)) {
            return ['success' => true, 'products' => $cached];
        }

        $products = $this->fetchProducts($line, $catalogId);

        if ($products === null) {
            return [
                'success' => false,
                'message' => $this->lastError ?? 'Failed to retrieve products from API.',
            ];
        }

        TenantSafeCache::put($cacheKey, $products, self::PRODUCT_CACHE_TTL);

        return ['success' => true, 'products' => $products];
    }

    /**
     * Flush catalog cache for a line (call when catalog is updated).
     */
    public function flushCatalogCache(WhatsappLine $line): void
    {
        TenantSafeCache::forget("commerce.catalogs.{$line->id}");
    }

    /**
     * Flush product cache for a specific catalog.
     */
    public function flushProductCache(WhatsappLine $line, string $catalogId): void
    {
        TenantSafeCache::forget("commerce.products.{$line->id}.{$catalogId}");
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /** @return list<array<string,mixed>>|null */
    private function fetchCatalogs(WhatsappLine $line): ?array
    {
        $this->lastError = null;

        try {
            $businessId = $this->resolveBusinessId($line);
            if ($businessId === null) {
                $this->lastError = 'Business ID not found for this WhatsApp line. Sync the line profile first.';

                return null;
            }

            $response = $this->camsClient->listProductCatalogs([
                'BusinessId' => $businessId,
                'CustSpaceId' => $line->alibaba_cust_space_id,
                'Fields' => self::CATALOG_FIELDS,
                'Limit' => (string) self::LIST_LIMIT,
            ]);

            if (! $response->successful()) {
                $this->lastError = 'Catalog API returned HTTP '.$response->status().'.';
                Log::warning('Commerce: catalog API returned non-200', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $this->extractListData($response);
            if ($data === null) {
                return null;
            }

            return array_values(array_map(
                fn (array $c) => $this->formatCatalog($c),
                array_values(array_filter($data, 'is_array')),
            ));
        } catch (\Throwable $e) {
            $this->lastError = 'Catalog API error: '.$e->getMessage();
            Log::error('Commerce: catalog fetch exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return list<array<string,mixed>>|null */
    private function fetchProducts(WhatsappLine $line, string $catalogId): ?array
    {
        $this->lastError = null;

        try {
            if (blank($line->waba_id)) {
                $this->lastError = 'WhatsApp line has no WABA ID configured.';

                return null;
            }

            $response = $this->camsClient->listProducts([
                'CatalogId' => $catalogId,
                'CustSpaceId' => $line->alibaba_cust_space_id,
                'Fields' => self::PRODUCT_FIELDS,
                'Limit' => (string) self::LIST_LIMIT,
                'WabaId' => $line->waba_id,
            ]);

            if (! $response->successful()) {
                $this->lastError = 'Product API returned HTTP '.$response->status().'.';
                Log::warning('Commerce: product API returned non-200', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $this->extractListData($response);
            if ($data === null) {
                return null;
            }

            return array_values(array_map(
                fn (array $p) => $this->formatProduct($p),
                array_values(array_filter($data, 'is_array')),
            ));
        } catch (\Throwable $e) {
            $this->lastError = 'Product API error: '.$e->getMessage();
            Log::error('Commerce: product fetch exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Business Manager ID required by ListProductCatalog.
     * Stored on the line as metadata.business_id (from QueryWabaBusinessInfo).
     */
    private function resolveBusinessId(WhatsappLine $line): ?string
    {
        $metadata = is_array($line->metadata) ? $line->metadata : [];
        $existing = $metadata['business_id'] ?? null;
        if (is_scalar($existing) && filled((string) $existing)) {
            return (string) $existing;
        }

        if (blank($line->waba_id) || blank($line->alibaba_cust_space_id)) {
            return null;
        }

        $response = $this->camsClient->queryWabaBusinessInfo([
            'CustSpaceId' => $line->alibaba_cust_space_id,
            'WabaId' => $line->waba_id,
        ]);

        if (! $response->successful()) {
            Log::warning('Commerce: QueryWabaBusinessInfo failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json)) {
            return null;
        }

        $code = $json['Code'] ?? $json['code'] ?? null;
        if (is_scalar($code) && strtoupper((string) $code) !== 'OK') {
            Log::warning('Commerce: QueryWabaBusinessInfo API error', [
                'code' => $code,
                'message' => $json['Message'] ?? $json['message'] ?? null,
            ]);

            return null;
        }

        $data = Arr::get($json, 'Data')
            ?? Arr::get($json, 'data')
            ?? [];

        if (! is_array($data)) {
            return null;
        }

        $businessId = Arr::get($data, 'businessId') ?? Arr::get($data, 'BusinessId');
        if (! is_scalar($businessId) || blank((string) $businessId)) {
            return null;
        }

        $metadata['business_id'] = (string) $businessId;

        $businessName = Arr::get($data, 'businessName') ?? Arr::get($data, 'BusinessName');
        if (is_string($businessName) && $businessName !== '') {
            $metadata['business_name'] = $businessName;
        }

        $line->metadata = $metadata;
        $line->save();

        return (string) $businessId;
    }

    /**
     * @return list<mixed>|null  null on API-level failure
     */
    private function extractListData(Response $response): ?array
    {
        $json = $response->json();
        if (! is_array($json)) {
            $this->lastError = 'Catalog API returned an invalid response.';

            return null;
        }

        $code = $json['Code'] ?? $json['code'] ?? null;
        if (is_scalar($code) && strtoupper((string) $code) !== 'OK') {
            $message = $json['Message'] ?? $json['message'] ?? null;
            $this->lastError = is_scalar($message) && filled((string) $message)
                ? (string) $message
                : 'Catalog API error: '.(string) $code;

            Log::warning('Commerce: CAMS list API error', [
                'code' => $code,
                'message' => $message,
                'request_id' => $json['RequestId'] ?? $json['requestId'] ?? null,
            ]);

            return null;
        }

        $success = $json['Success'] ?? $json['success'] ?? null;
        if ($success === false) {
            $message = $json['Message'] ?? $json['message'] ?? 'Catalog API reported failure.';
            $this->lastError = is_scalar($message) ? (string) $message : 'Catalog API reported failure.';

            return null;
        }

        $data = Arr::get($json, 'Model.Data')
            ?? Arr::get($json, 'model.data')
            ?? Arr::get($json, 'body.model.data')
            ?? Arr::get($json, 'Data')
            ?? Arr::get($json, 'data');

        if ($data === null) {
            // Successful empty list is still valid.
            return [];
        }

        if (! is_array($data)) {
            $this->lastError = 'Catalog API returned unexpected data shape.';

            return null;
        }

        return $data;
    }

    /** @param array<string,mixed> $catalog */
    private function formatCatalog(array $catalog): array
    {
        $business = $catalog['business'] ?? [];
        if (! is_array($business)) {
            $business = [];
        }

        return [
            'id' => (string) ($catalog['id'] ?? ''),
            'name' => (string) ($catalog['name'] ?? 'Unknown'),
            'product_count' => (int) ($catalog['product_count'] ?? 0),
            'vertical' => (string) ($catalog['vertical'] ?? ''),
            'image_url' => (string) ($catalog['default_image_url'] ?? ''),
            'business_name' => (string) ($business['name'] ?? ''),
            'business_id' => (string) ($business['id'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $product */
    private function formatProduct(array $product): array
    {
        $rawPrice = (string) ($product['price'] ?? '');
        $price = $rawPrice !== '' ? '₹ '.ltrim($rawPrice, '₹ ') : 'N/A';

        return [
            'id' => (string) ($product['id'] ?? ''),
            'retailer_id' => (string) ($product['retailer_id'] ?? $product['id'] ?? ''),
            'name' => (string) ($product['name'] ?? 'No Name'),
            'description' => (string) ($product['description'] ?? ''),
            'brand' => (string) ($product['brand'] ?? ''),
            'price' => $price,
            'condition' => (string) ($product['condition'] ?? ''),
            'availability' => (string) ($product['availability'] ?? ''),
            'image_url' => (string) ($product['image_url'] ?? ''),
            'inventory' => (int) ($product['inventory'] ?? 0),
        ];
    }
}
