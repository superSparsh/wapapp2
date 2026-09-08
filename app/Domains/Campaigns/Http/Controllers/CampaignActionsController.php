<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Controllers;

use App\Domains\Campaigns\Services\CampaignServiceAdapter;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignActionsController extends Controller
{
    public function __construct(
        private readonly CampaignServiceAdapter $adapter,
    ) {}

    public function testMessage(Request $request, Campaign $bulkCampaign): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'template_variables' => ['nullable', 'array'],
        ]);

        $this->adapter->sendTestMessage(
            campaign: $bulkCampaign,
            phone: (string) $validated['phone'],
            templateVariables: (array) ($validated['template_variables'] ?? []),
        );

        return response()->json(['success' => true, 'message' => 'Test message queued.']);
    }

    public function resendFailed(Campaign $bulkCampaign): JsonResponse
    {
        $count = $this->adapter->resendFailed($bulkCampaign);

        return response()->json([
            'success' => true,
            'resent' => $count,
        ]);
    }

    public function calculateCost(Campaign $bulkCampaign): JsonResponse
    {
        return response()->json($this->adapter->calculateCost($bulkCampaign));
    }

    public function importRecipients(Request $request, Campaign $bulkCampaign): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->adapter->importRecipients($bulkCampaign, $validated['file']);

        return response()->json([
            'success' => true,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function storeWebhook(Request $request, Campaign $bulkCampaign): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string', 'max:64'],
            'secret_key' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $webhook = $this->adapter->storeWebhook($bulkCampaign, $validated);

        return response()->json([
            'success' => true,
            'webhook' => $webhook,
        ], 201);
    }
}
