<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Http\Controllers;

use App\Domains\Webhooks\Services\WebhookDeliveryService;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Support\PublicId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class WebhookDeliveryController extends Controller
{
    public function __construct(
        private readonly WebhookDeliveryService $service,
    ) {}

    /**
     * Logs page with metrics + filtered table.
     */
    public function index(Request $request): View
    {
        $subscription = $request->filled('subscription_id')
            ? PublicId::find(WebhookSubscription::class, (string) $request->input('subscription_id'))
            : null;

        $metrics = $this->service->metrics($subscription?->id);
        $deliveries = $this->service->logs($request, subscriptionId: $subscription?->id);
        $subscriptions = WebhookSubscription::query()->select(['id', 'uuid', 'description'])->orderBy('description')->get();

        return view('webhooks.logs', [
            'metrics' => $metrics,
            'deliveries' => $deliveries,
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Delivery detail page.
     */
    public function show(WebhookDelivery $webhookDelivery): View
    {
        $delivery = $this->service->show($webhookDelivery);

        return view('webhooks.log-detail', ['delivery' => $delivery]);
    }

    /**
     * Retry a failed delivery.
     */
    public function retry(WebhookDelivery $webhookDelivery): RedirectResponse
    {
        $this->service->retry($webhookDelivery);

        return redirect()->back()->with('status', 'Webhook retry initiated.');
    }

    /**
     * Delete a delivery log.
     */
    public function destroy(WebhookDelivery $webhookDelivery): RedirectResponse
    {
        $this->service->destroy($webhookDelivery);

        return redirect()->route('webhooks.logs')->with('status', 'Delivery log deleted.');
    }
}
