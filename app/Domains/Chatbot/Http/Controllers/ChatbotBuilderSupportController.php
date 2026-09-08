<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Http\Controllers;

use App\Domains\Chatbot\Services\ChatbotBuilderDataService;
use App\Domains\Commerce\Services\CatalogService;
use App\Domains\Inbox\Services\InboxQueryService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowInteractiveService;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ChatbotBuilderSupportController
{
    public function __construct(
        private readonly CatalogService $catalogService,
        private readonly InboxQueryService $inboxQueryService,
        private readonly ChatbotBuilderDataService $builderDataService,
        private readonly WhatsappFlowInteractiveService $flowInteractiveService,
    ) {}

    public function mediaUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:16384'],
        ]);

        $path = $request->file('file')->store('chatbot/media', 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'url' => $url,
            'fileUrl' => $url,
            'fileName' => basename($path),
        ]);
    }

    public function publicFileUpload(Request $request): JsonResponse
    {
        return $this->mediaUpload($request);
    }

    public function catalogData(): JsonResponse
    {
        $line = $this->inboxQueryService->resolveDefaultLine();
        $result = $this->catalogService->getCatalogs($line);

        return response()->json($result);
    }

    public function productData(Request $request): JsonResponse
    {
        $request->validate([
            'catalogId' => ['required', 'string'],
        ]);

        $line = $this->inboxQueryService->resolveDefaultLine();
        $result = $this->catalogService->getProducts($line, $request->string('catalogId')->toString());

        return response()->json($result);
    }

    public function flowData(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'flow_data' => [
                'data' => $this->flowInteractiveService->legacyFlowList(),
            ],
        ]);
    }

    public function flowJsonCode(string $flowIdentifier): JsonResponse
    {
        $flow = $this->flowInteractiveService->findByIdentifier($flowIdentifier);

        if ($flow === null) {
            return response()->json([
                'status' => false,
                'message' => 'Flow not found.',
            ], 404);
        }

        $metaJson = is_array($flow->meta_json) ? $flow->meta_json : null;
        $flowData = $metaJson ?? (is_array($flow->flow_json) ? $flow->flow_json : []);

        return response()->json([
            'status' => true,
            'flow_data' => $flowData,
        ]);
    }

    public function templateCards(Template $template): JsonResponse
    {
        return response()->json($this->builderDataService->templateCards($template));
    }

    public function testWebhook(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url'],
        ]);

        try {
            $response = Http::timeout(10)->post($request->string('url')->toString(), [
                'test' => true,
                'source' => 'chatbot_builder',
            ]);

            return response()->json([
                'success' => true,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
