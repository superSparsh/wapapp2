<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Services\ShopifyWebhookIngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopifyWebhookController extends Controller
{
    public function __construct(
        private readonly ShopifyWebhookIngestService $ingest,
    ) {}

    public function handle(Request $request, string $topic = ''): JsonResponse
    {
        if (! $this->ingest->verifyHmac($request)) {
            return response()->json(['message' => 'Invalid HMAC'], 401);
        }

        $shopDomain = (string) $request->header('X-Shopify-Shop-Domain', '');
        $tenantId = $this->ingest->resolveTenantId($shopDomain);
        if ($tenantId === null) {
            return response()->json(['message' => 'Unknown shop'], 404);
        }

        $pathTopic = $topic !== ''
            ? str_replace('/', '_', trim($topic, '/'))
            : $this->ingest->topicFromPath($request->path());

        $payload = $request->json()->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        $headers = collect($request->headers->all())
            ->map(fn (array $values): string => implode(', ', $values))
            ->all();

        $this->ingest->enqueue($tenantId, $shopDomain, $pathTopic, $payload, $headers);

        return response()->json(['code' => 0, 'msg' => 'Success']);
    }
}
