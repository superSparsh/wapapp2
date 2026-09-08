<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PopulateRecipientsRequest;
use App\Http\Requests\SendTestMessageRequest;
use App\Http\Requests\StoreWebhookRequest;
use App\Repositories\Interfaces\CampaignRepositoryInterface;
use App\Services\CampaignCostCalculator;
use App\Services\CampaignCsvImportService;
use App\Services\CampaignQueryService;
use App\Services\CampaignResendService;
use App\Services\CampaignSendService;
use App\Services\CampaignService;
use App\Services\CampaignTestMessageService;
use App\Services\CampaignWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignActionController extends Controller
{
    public function __construct(
        private readonly CampaignQueryService $queryService,
        private readonly CampaignService $campaignService,
        private readonly CampaignTestMessageService $testMessageService,
        private readonly CampaignResendService $resendService,
        private readonly CampaignCostCalculator $costCalculator,
        private readonly CampaignCsvImportService $csvImportService,
        private readonly CampaignWebhookService $webhookService,
        private readonly CampaignSendService $sendService,
        private readonly CampaignRepositoryInterface $campaignRepo,
    ) {}

    public function testMessage(SendTestMessageRequest $request, string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $result = $this->testMessageService->send(
            campaign: $campaign,
            phone: (string) $request->input('phone'),
            templateVariables: (array) ($request->input('template_variables') ?? []),
        );

        return response()->json([
            'success' => $result['success'],
            'message_id' => $result['message_id'],
            'message' => $result['success'] ? 'Test message sent.' : 'Test message failed.',
            'error' => $result['error'],
        ], $result['success'] ? 200 : 422);
    }

    public function resendFailed(string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $count = $this->resendService->resendFailed($campaign);

        return response()->json([
            'success' => true,
            'resent' => $count,
        ]);
    }

    public function calculateCost(Request $request, string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $category = $request->query('category', 'MARKETING');

        return response()->json($this->costCalculator->estimate($campaign, $category));
    }

    public function importRecipients(Request $request, string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->csvImportService->import($campaign, $request->file('file'));

        return response()->json([
            'success' => true,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);
    }

    public function populateRecipients(PopulateRecipientsRequest $request, string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $count = $this->campaignService->populateRecipients($campaign, (array) $request->input('recipients'));

        return response()->json([
            'success' => true,
            'recipients_count' => $count,
            'total_recipients' => $campaign->refresh()->total_recipients,
        ]);
    }

    public function storeWebhook(StoreWebhookRequest $request, string $uuid): JsonResponse
    {
        $campaign = $this->queryService->findByUuid($uuid)
            ?? (is_numeric($uuid) ? $this->queryService->findById((int) $uuid) : null);

        if ($campaign === null) {
            return response()->json(['error' => 'Campaign not found.'], 404);
        }

        $webhook = $this->webhookService->store($campaign, $request->validated());

        return response()->json([
            'success' => true,
            'webhook' => $webhook,
        ], 201);
    }

    public function processDue(): JsonResponse
    {
        $due = $this->campaignRepo->getDueScheduledCampaigns();
        $processed = 0;

        foreach ($due as $campaign) {
            $this->sendService->queueCampaign($campaign);
            $processed++;
        }

        return response()->json([
            'success' => true,
            'processed' => $processed,
        ]);
    }
}
