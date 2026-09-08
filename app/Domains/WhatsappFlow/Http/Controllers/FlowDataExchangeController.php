<?php

declare(strict_types=1);

namespace App\Domains\WhatsappFlow\Http\Controllers;

use App\Domains\WhatsappFlow\Services\FlowDataExchangeService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowExchangeRegistryService;
use App\Domains\WhatsappFlow\Services\WhatsappFlowQueryService;
use App\Http\Controllers\Controller;
use App\Models\WhatsappFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FlowDataExchangeController extends Controller
{
    public function __construct(
        private readonly WhatsappFlowExchangeRegistryService $registryService,
        private readonly WhatsappFlowQueryService $queryService,
        private readonly FlowDataExchangeService $exchangeService,
    ) {}

    public function handle(Request $request, string $flowToken): JsonResponse
    {
        $connection = (string) config('tenancy.database.central_connection', config('database.default'));

        if (Schema::connection($connection)->hasTable('whatsapp_flow_exchange_registry')) {
            $resolved = $this->registryService->resolve($flowToken);

            if ($resolved !== null) {
                tenancy()->initialize($resolved['tenant']);

                try {
                    $flow = WhatsappFlow::query()->find($resolved['flow_id']);

                    if ($flow !== null && $flow->isActive()) {
                        return $this->processExchange($flow, $request);
                    }
                } finally {
                    tenancy()->end();
                }
            }
        }

        if (tenancy()->initialized) {
            $flow = $this->queryService->findByDataExchangeToken($flowToken);

            if ($flow !== null) {
                return $this->processExchange($flow, $request);
            }
        }

        return response()->json(['error' => 'Flow not found.'], 404);
    }

    private function processExchange(WhatsappFlow $flow, Request $request): JsonResponse
    {
        $payload = $request->all();

        if ($payload === []) {
            return response()->json(['error' => 'Empty payload.'], 422);
        }

        $this->exchangeService->handleDataExchange($flow, $payload);
        $screenResponse = $this->exchangeService->buildScreenResponse($flow, $payload);

        return response()->json($screenResponse);
    }
}
