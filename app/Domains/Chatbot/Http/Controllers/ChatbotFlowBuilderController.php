<?php

declare(strict_types=1);

namespace App\Domains\Chatbot\Http\Controllers;

use App\Domains\Chatbot\Http\Requests\SaveFlowDataRequest;
use App\Domains\Chatbot\Services\ChatbotBuilderDataService;
use App\Domains\Chatbot\Services\ChatbotFlowService;
use App\Domains\Chatbot\Support\FlowNodeDataMapper;
use App\Models\ChatbotFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotFlowBuilderController
{
    public function __construct(
        private readonly ChatbotFlowService $flowService,
        private readonly FlowNodeDataMapper $nodeDataMapper,
        private readonly ChatbotBuilderDataService $builderDataService,
    ) {}

    /**
     * Legacy-compatible builder bootstrap payload.
     */
    public function builderData(ChatbotFlow $chatbotFlow): JsonResponse
    {
        $payload = $this->builderDataService->payload($chatbotFlow);

        return response()->json([
            'message' => 'success',
            'success' => true,
            'templates' => $payload['templates'],
            'interactiveMessages' => $payload['interactiveMessages'],
            'automationBot' => $payload['automationBot'],
            'aiBots' => $payload['aiBots'] ?? [],
        ]);
    }

    /**
     * Get the flow data for the builder.
     */
    public function getData(ChatbotFlow $chatbotFlow): JsonResponse
    {
        $data = $chatbotFlow->exported_data ?? ['nodes' => [], 'edges' => []];

        return response()->json([
            'success' => true,
            'data' => $data,
            'flow' => [
                'id' => $chatbotFlow->id,
                'uuid' => $chatbotFlow->uuid,
                'name' => $chatbotFlow->name,
                'status' => $chatbotFlow->status->value,
                'node_count' => $chatbotFlow->nodeCount(),
            ],
        ]);
    }

    /**
     * Save the flow data from the builder.
     */
    public function saveData(SaveFlowDataRequest $request, ChatbotFlow $chatbotFlow): JsonResponse
    {
        if ($request->filled('customData')) {
            return $this->saveLegacyPayload($request, $chatbotFlow);
        }

        $validated = $request->validated();

        $flowData = [
            'nodes' => $validated['nodes'],
            'edges' => $validated['edges'] ?? [],
        ];

        $this->flowService->saveFlowData($chatbotFlow, $flowData);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Flow data saved successfully.',
            'node_count' => $chatbotFlow->fresh()->nodeCount(),
        ]);
    }

    public function clearCache(ChatbotFlow $chatbotFlow): JsonResponse
    {
        $this->flowService->clearCache($chatbotFlow);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Cache cleared successfully.',
        ]);
    }

    /**
     * Export flow data as JSON.
     */
    public function export(ChatbotFlow $chatbotFlow): JsonResponse
    {
        return response()->json([
            'success' => true,
            'flow' => [
                'name' => $chatbotFlow->name,
                'status' => $chatbotFlow->status->value,
                'exported_data' => $chatbotFlow->exported_data,
                'exported_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Import flow data from JSON as a new flow.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'exported_data' => ['required', 'array'],
            'exported_data.nodes' => ['required', 'array'],
            'exported_data.edges' => ['sometimes', 'array'],
        ]);

        $flow = $this->flowService->create([
            'name' => $request->input('name'),
        ]);

        $this->flowService->saveLegacyFlowData($flow, $request->input('exported_data'));

        return response()->json([
            'success' => true,
            'message' => 'Flow imported successfully.',
            'flow_id' => $flow->id,
            'uuid' => $flow->uuid,
            'edit_url' => route('chatbot.edit', $flow),
        ], 201);
    }

    /**
     * Import flow data into an existing flow (replaces canvas).
     */
    public function importToFlow(Request $request, ChatbotFlow $chatbotFlow): JsonResponse
    {
        $request->validate([
            'exported_data' => ['required', 'array'],
            'exported_data.nodes' => ['required', 'array'],
            'exported_data.edges' => ['sometimes', 'array'],
        ]);

        $payload = $request->input('exported_data');

        if (isset($payload['flow']['exported_data']) && is_array($payload['flow']['exported_data'])) {
            $payload = $payload['flow']['exported_data'];
        }

        $this->flowService->saveLegacyFlowData($chatbotFlow, $payload);

        return response()->json([
            'success' => true,
            'message' => 'Flow imported into the current builder.',
            'data' => $chatbotFlow->fresh()->exported_data,
            'node_count' => $chatbotFlow->nodeCount(),
        ]);
    }

    private function saveLegacyPayload(Request $request, ChatbotFlow $chatbotFlow): JsonResponse
    {
        $raw = $request->input('customData');
        $flowData = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (is_string($flowData)) {
            $flowData = json_decode($flowData, true);
        }

        if (! is_array($flowData) || ! isset($flowData['nodes'])) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Invalid flow payload: unable to decode nodes/edges',
            ], 422);
        }

        $this->flowService->saveLegacyFlowData($chatbotFlow, $flowData);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Flow saved successfully.',
            'automationBot' => [
                'id' => $chatbotFlow->id,
                'uuid' => $chatbotFlow->uuid,
                'name' => $chatbotFlow->name,
                'exported_data' => $chatbotFlow->fresh()->exported_data,
            ],
        ]);
    }
}
