<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Http\Controllers;

use App\Domains\WhatsappFlow\Http\Requests\SaveFlowJsonRequest;
use App\Domains\WhatsappFlow\Services\WhatsappFlowService;
use App\Http\Controllers\Controller;
use App\Models\WhatsappFlow;
use Illuminate\Http\JsonResponse;

class WhatsappFlowBuilderController extends Controller
{
    public function __construct(
        private readonly WhatsappFlowService $flowService,
    ) {}

    public function getData(WhatsappFlow $whatsappFlow): JsonResponse
    {
        return response()->json([
            'success' => true,
            'flow' => [
                'id' => $whatsappFlow->id,
                'uuid' => $whatsappFlow->uuid,
                'name' => $whatsappFlow->name,
                'flow_json' => $whatsappFlow->flow_json ?? ['screens' => [], 'first_screen' => null],
            ],
        ]);
    }

    public function saveData(SaveFlowJsonRequest $request, WhatsappFlow $whatsappFlow): JsonResponse
    {
        $flow = $this->flowService->saveFlowJson($whatsappFlow, $request->validated()['flow_json']);

        return response()->json([
            'success' => true,
            'screen_count' => $flow->screenCount(),
            'field_count' => $flow->fieldCount(),
            'draft_synced' => $flow->isDraftSynced(),
        ]);
    }

    public function export(WhatsappFlow $whatsappFlow): JsonResponse
    {
        return response()->json([
            'flow' => [
                'name' => $whatsappFlow->name,
                'status' => $whatsappFlow->status->value,
                'flow_json' => $whatsappFlow->flow_json,
                'exported_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function import(SaveFlowJsonRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $name = $request->input('flow_json.name', $request->input('name', 'Imported Flow'));

        $flow = $this->flowService->create([
            'name' => $name,
        ]);

        $this->flowService->saveFlowJson($flow, $validated['flow_json']);

        return response()->json([
            'success' => true,
            'flow_id' => $flow->id,
            'name' => $flow->name,
        ], 201);
    }
}
