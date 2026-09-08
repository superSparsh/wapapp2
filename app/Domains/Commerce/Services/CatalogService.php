<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Services;

use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Models\WhatsappLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Fetches Facebook catalogs and products via Alibaba CAMS API.
 * Results are cached per-line to avoid hammering the API on every page load.
 */
class CatalogService
{
    private const CATALOG_CACHE_TTL  = 300;  // 5 minutes
    private const PRODUCT_CACHE_TTL  = 300;  // 5 minutes
    private const CATALOG_FIELDS     = 'id,business,default_image_url,name,product_count,vertical';
    private const PRODUCT_FIELDS     = 'id,retailer_id,name,description,brand,price,condition,availability,image_url,inventory';
    private const LIST_LIMIT         = 1000;

    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /**
     * Returns catalogs for the given WhatsApp line.
     * Uses the line's alibaba_cust_space_id + waba_id (business_id proxy).
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

        $catalogs = Cache::remember($cacheKey, self::CATALOG_CACHE_TTL, function () use ($line): ?array {
            return $this->fetchCatalogs($line);
        });

        if ($catalogs === null) {
            return ['success' => false, 'message' => 'Failed to retrieve catalogs from API.'];
        }

        return ['success' => true, 'catalogs' => $catalogs];
    }

    /**
     * Returns products for a given catalog.
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

        $products = Cache::remember($cacheKey, self::PRODUCT_CACHE_TTL, function () use ($line, $catalogId): ?array {
            return $this->fetchProducts($line, $catalogId);
        });

        if ($products === null) {
            return ['success' => false, 'message' => 'Failed to retrieve products from API.'];
        }

        return ['success' => true, 'products' => $products];
    }

    /**
     * Flush catalog cache for a line (call when catalog is updated).
     */
    public function flushCatalogCache(WhatsappLine $line): void
    {
        Cache::forget("commerce.catalogs.{$line->id}");
    }

    /**
     * Flush product cache for a specific catalog.
     */
    public function flushProductCache(WhatsappLine $line, string $catalogId): void
    {
        Cache::forget("commerce.products.{$line->id}.{$catalogId}");
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /** @return list<array<string,mixed>>|null */
    private function fetchCatalogs(WhatsappLine $line): ?array
    {
        try {
            $response = $this->camsClient->listProductCatalogs([
                'CustSpaceId' => $line->alibaba_cust_space_id,
                'Fields'      => self::CATALOG_FIELDS,
                'Limit'       => (string) self::LIST_LIMIT,
            ]);

            if (! $response->successful()) {
                Log::warning('Commerce: catalog API returned non-200', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            $data = $response->json('model.data', $response->json('Model.Data', []));

            if (! is_array($data)) {
                return [];
            }

            return array_values(array_map(fn (array $c) => $this->formatCatalog($c), $data));
        } catch (\Throwable $e) {
            Log::error('Commerce: catalog fetch exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return list<array<string,mixed>>|null */
    private function fetchProducts(WhatsappLine $line, string $catalogId): ?array
    {
        try {
            $response = $this->camsClient->listProducts([
                'CustSpaceId' => $line->alibaba_cust_space_id,
                'CatalogId'   => $catalogId,
                'Fields'      => self::PRODUCT_FIELDS,
                'Limit'       => (string) self::LIST_LIMIT,
                'WabaId'      => $line->waba_id ?? '',
            ]);

            if (! $response->successful()) {
                Log::warning('Commerce: product API returned non-200', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return null;
            }

            $data = $response->json('model.data', $response->json('Model.Data', []));

            if (! is_array($data)) {
                return [];
            }

            return array_values(array_map(fn (array $p) => $this->formatProduct($p), $data));
        } catch (\Throwable $e) {
            Log::error('Commerce: product fetch exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @param array<string,mixed> $catalog */
    private function formatCatalog(array $catalog): array
    {
        return [
            'id'            => $catalog['id'] ?? '',
            'name'          => $catalog['name'] ?? 'Unknown',
            'product_count' => (int) ($catalog['product_count'] ?? 0),
            'vertical'      => $catalog['vertical'] ?? '',
            'image_url'     => $catalog['default_image_url'] ?? '',
            'business_name' => $catalog['business']['name'] ?? '',
            'business_id'   => $catalog['business']['id'] ?? '',
        ];
    }

    /** @param array<string,mixed> $product */
    private function formatProduct(array $product): array
    {
        $rawPrice = (string) ($product['price'] ?? '');
        $price    = $rawPrice !== '' ? '₹ '.ltrim($rawPrice, '₹ ') : 'N/A';

        return [
            'id'           => $product['id'] ?? '',
            'retailer_id'  => $product['retailer_id'] ?? $product['id'] ?? '',
            'name'         => $product['name'] ?? 'No Name',
            'description'  => $product['description'] ?? '',
            'brand'        => $product['brand'] ?? '',
            'price'        => $price,
            'condition'    => $product['condition'] ?? '',
            'availability' => $product['availability'] ?? '',
            'image_url'    => $product['image_url'] ?? '',
            'inventory'    => (int) ($product['inventory'] ?? 0),
        ];
    }
}
