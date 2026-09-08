<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Controllers;

use App\Domains\Webhooks\Http\Requests\StoreWebhookSubscriptionRequest;
use App\Domains\Webhooks\Http\Requests\UpdateWebhookSubscriptionRequest;
use App\Domains\Webhooks\Services\WebhookSubscriptionService;
use App\Models\MailList;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class WebhookSubscriptionController extends Controller
{
    public function __construct(
        private readonly WebhookSubscriptionService $service,
    ) {}

    /**
     * List subscriptions + creation form.
     */
    public function index(Request $request): View
    {
        $subscriptions = $this->service->index($request->get('search'));
        $mailLists = MailList::query()->select(['id', 'name'])->orderBy('name')->get();

        return view('webhooks.index', [
            'subscriptions' => $subscriptions,
            'mailLists' => $mailLists,
        ]);
    }

    /**
     * Store a new subscription.
     */
    public function store(StoreWebhookSubscriptionRequest $request): RedirectResponse
    {
        $this->service->store($request->validated());

        return redirect()->route('webhooks.index')
            ->with('status', 'Webhook created successfully.');
    }

    /**
     * Update a subscription.
     */
    public function update(UpdateWebhookSubscriptionRequest $request, WebhookSubscription $webhookSubscription): RedirectResponse
    {
        $this->service->update($webhookSubscription, $request->validated());

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
            'status' => $delivery->status->value,
            'response_status' => $delivery->response_status,
            'duration_ms' => $delivery->duration_ms,
            'error_message' => $delivery->error_message,
        ]);
    }
}
