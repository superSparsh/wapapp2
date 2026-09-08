<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Controllers;

use App\Domains\Webhooks\Services\InboundWebhookRecorder;
use App\Enums\InboundWebhookEventType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlibabaWebhookController extends Controller
{
    public function message(Request $request, InboundWebhookRecorder $recorder): JsonResponse
    {
        $recorder->record(
            eventType: InboundWebhookEventType::Message,
            rawBody: $request->getContent(),
            headers: $this->captureHeaders($request),
        );

        return response()->json(['code' => 0, 'msg' => 'Success']);
    }

    public function status(Request $request, InboundWebhookRecorder $recorder): JsonResponse
    {
        $recorder->record(
            eventType: InboundWebhookEventType::Status,
            rawBody: $request->getContent(),
            headers: $this->captureHeaders($request),
        );

        return response()->json(['code' => 0, 'msg' => 'Success']);
    }

    /**
     * @return array<string, string>
     */
    private function captureHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->map(fn (array $values): string => implode(', ', $values))
            ->all();
    }
}
