<?php

declare(strict_types=1);

namespace App\Domains\Campaigns\Http\Controllers;

use App\Domains\Campaigns\Services\CampaignServiceAdapter;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    public function resendFailed(Request $request, Campaign $bulkCampaign): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['nullable', 'in:create,inplace'],
            'list_name' => ['required_unless:mode,inplace', 'nullable', 'string', 'max:255'],
            'campaign_name' => ['required_unless:mode,inplace', 'nullable', 'string', 'max:255'],
            'send_option' => ['required_unless:mode,inplace', 'nullable', 'in:now,schedule'],
        ]);

        // Legacy default: create new list + campaign. Optional inplace requeue.
        if (($validated['mode'] ?? 'create') === 'inplace') {
            $count = $this->adapter->resendFailed($bulkCampaign);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'resent' => $count,
                ]);
            }

            return redirect()
                ->route('campaigns.statistics', $bulkCampaign)
                ->with('status', $count > 0
                    ? "Requeued {$count} failed recipient(s)."
                    : 'No failed recipients to resend.');
        }

        $result = $this->adapter->createCampaignFromFailed(
            $bulkCampaign,
            (string) ($validated['list_name'] ?? ''),
            (string) ($validated['campaign_name'] ?? ''),
            (string) ($validated['send_option'] ?? 'now'),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'imported' => $result['imported'],
                'launched' => $result['launched'],
                'campaign_uuid' => $result['campaign']->uuid,
                'list_uuid' => $result['list']->uuid,
                'redirect' => $result['launched']
                    ? route('campaigns.statistics', $result['campaign'])
                    : route('campaigns.edit', $result['campaign']),
            ]);
        }

        if ($result['launched']) {
            return redirect()
                ->route('campaigns.statistics', $result['campaign'])
                ->with('status', "Created \"{$result['campaign']->name}\" with {$result['imported']} failed contact(s) and started sending.");
        }

        return redirect()
            ->route('campaigns.edit', $result['campaign'])
            ->with('status', "Created \"{$result['campaign']->name}\" with {$result['imported']} failed contact(s). Schedule or send when ready.");
    }

    public function resendOptIn(Campaign $bulkCampaign): RedirectResponse|JsonResponse
    {
        $result = $this->adapter->resendOptInToFailed($bulkCampaign);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                ...$result,
            ]);
        }

        return redirect()
            ->route('campaigns.statistics', $bulkCampaign)
            ->with('status', "Opt-in resent: {$result['sent']} sent, {$result['skipped']} skipped (of {$result['attempted']} attempted).");
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
