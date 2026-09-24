<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Http\Controllers;

use App\Domains\WhatsappFlow\Support\WhatsappFlowMetaJsonConverter;
use App\Http\Controllers\Controller;
use App\Models\WhatsappFlow;
use Illuminate\Http\JsonResponse;

/**
 * Public (no-auth) JSON asset for CAMS updateFlowJsonAsset FilePath.
 * Tenant is resolved from the URL path.
 */
class WhatsappFlowPublicAssetController extends Controller
{
    public function show(string $uuid): JsonResponse
    {
        $flow = WhatsappFlow::query()->where('uuid', $uuid)->firstOrFail();

        $metaJson = is_array($flow->meta_json) ? $flow->meta_json : [];

        if ($metaJson === [] && is_array($flow->flow_json) && $flow->flow_json !== []) {
            $metaJson = WhatsappFlowMetaJsonConverter::convert($flow->flow_json);
        }

        abort_if($metaJson === [], 404);

        return response()->json($metaJson, 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }
}
