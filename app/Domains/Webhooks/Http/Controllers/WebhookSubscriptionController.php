<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Controllers;

use App\Domains\Integration\Services\PhoneLineService;
use App\Domains\Webhooks\Http\Requests\StoreWebhookSubscriptionRequest;
use App\Domains\Webhooks\Http\Requests\UpdateWebhookSubscriptionRequest;
use App\Domains\Webhooks\Services\WebhookSubscriptionService;
use App\Models\MailList;
use App\Models\WebhookSubscription;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class WebhookSubscriptionController extends Controller
{
    public function __construct(
        private readonly WebhookSubscriptionService $service,
        private readonly PhoneLineService $phoneLineService,
    ) {}

    /**
     * List subscriptions + creation form.
     */
    public function index(Request $request): View
    {
        $subscriptions = $this->service->index($request->get('search'));
        $mailLists = MailList::query()->select(['id', 'uuid', 'name'])->orderBy('name')->get();
        $activeLine = PhoneLineService::isLocked()
            ? $this->phoneLineService->lockedLine()
            : $this->phoneLineService->defaultLine();

        return view('webhooks.index', [
            'subscriptions' => $subscriptions,
            'mailLists' => $mailLists,
            'activeLine' => $activeLine,
        ]);
    }

    /**
     * Store a new subscription.
     */
    public function store(StoreWebhookSubscriptionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $list = PublicId::find(MailList::class, $data['audience_list_id'] ?? null);
        $data['audience_list_id'] = $list?->id;

        // Legacy: bind webhook to locked line, else default line.
        $line = PhoneLineService::isLocked()
            ? $this->phoneLineService->lockedLine()
            : $this->phoneLineService->defaultLine();
        $data['whatsapp_line_id'] = $line?->id;

        $this->service->store($data);

        return redirect()->route('webhooks.index')
            ->with('status', 'Webhook created successfully.');
    }

    /**
     * Update a subscription.
     */
    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription): RedirectResponse
    {
        $data = $request->validated();
        $list = PublicId::find(MailList::class, $data['audience_list_id'] ?? null);
        $data['audience_list_id'] = $list?->id;

        $this->service->update($webhookSubscription, $data);

        return redirect()->route('webhooks.index')
            ->with('status', 'Webhook updated successfully.');
    }

    /**
     * Delete a subscription.
     */
    public function destroy(WebhookSubscription $webhookSubscription): RedirectResponse
    {
        $this->service->destroy($webhookSubscription);

        return redirect()->route('webhooks.index')
            ->with('status', 'Webhook deleted successfully.');
    }

    /**
     * Toggle Active/Inactive (AJAX).
     */
    public function toggleStatus(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $updated = $this->service->toggleStatus($webhookSubscription);

        return response()->json([
            'status' => $updated->status->value,
            'label' => $updated->status === \App\Enums\WebhookSubscriptionStatus::Active ? 'Active' : 'Inactive',
        ]);
    }

    /**
     * Regenerate secret key (AJAX).
     */
    public function regenerateSecret(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $newKey = $this->service->regenerateSecret($webhookSubscription);

        return response()->json(['secret_key' => $newKey]);
    }

    /**
     * Send a test payload (AJAX).
     */
    public function testDelivery(WebhookSubscription $webhookSubscription): JsonResponse
    {
        $delivery = $this->service->testDelivery($webhookSubscription);

        return response()->json([
            'success' => $delivery->status === \App\Enums\WebhookDeliveryStatus::Sent,
            'status' => $delivery->status->value,
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'error_message' => $delivery->error_message,
            'message' => $delivery->status === \App\Enums\WebhookDeliveryStatus::Sent
                ? 'Webhook test succeeded (HTTP '.($delivery->response_status ?? 200).').'
                : ($delivery->error_message ?: 'Webhook test failed.'),
        ]);
    }

    /**
     * Test an unsaved webhook URL (legacy parity: webhook.test with { url }).
     */
    public function testUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $result = $this->service->testUrl($validated['url']);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }
}
