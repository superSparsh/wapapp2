<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Http\Controllers;

use App\Domains\WhatsappFlow\Support\WhatsappFlowMetaJsonConverter;
use App\Http\Controllers\Controller;
use App\Models\WhatsappFlow;
use Illuminate\Http\Response;

/**
 * Public (no-auth) JSON asset for CAMS updateFlowJsonAsset FilePath.
 * Tenant is resolved from the URL path.
 *
 * Always regenerates from flow_json so converter fixes apply immediately
 * (CAMS must never keep downloading a stale meta_json / static file).
 */
class WhatsappFlowPublicAssetController extends Controller
{
    public function show(string $uuid): Response
    {
        $flow = WhatsappFlow::query()->where('uuid', $uuid)->firstOrFail();

        $metaJson = [];

        $hasScreens = is_array($flow->flow_json)
            && is_array($flow->flow_json['screens'] ?? null)
            && $flow->flow_json['screens'] !== [];

        if ($hasScreens) {
            $metaJson = WhatsappFlowMetaJsonConverter::convert($flow->flow_json);
        } elseif (is_array($flow->meta_json) && $flow->meta_json !== []) {
            $metaJson = $flow->meta_json;
        }

        abort_if($metaJson === [], 404);

        return response(
            json_encode($metaJson, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ],
        );
    }
}
