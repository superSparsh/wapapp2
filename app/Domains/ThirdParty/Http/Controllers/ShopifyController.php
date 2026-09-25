<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\Templates\Enums\TemplateStatus;
use App\Domains\ThirdParty\Http\Requests\Shopify\SaveShopifyDomainRequest;
use App\Domains\ThirdParty\Http\Requests\Shopify\SaveShopifyScopesRequest;
use App\Domains\ThirdParty\Services\ShopifyService;
use App\Models\MailList;
use App\Models\Template;
use App\Support\ListingSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ShopifyController extends Controller
{
    public function __construct(
        private readonly ShopifyService $shopify,
    ) {}

    public function index(Request $request): View
    {
        $userId = (int) auth()->id();
        $parsed = ListingSort::fromRequest(
            $request,
            ['created_at', 'sent_at', 'event_type', 'status', 'whatsapp_number'],
            'created_at',
            'desc',
        );
        $sendData = $this->shopify->sendData(
            $userId,
            50,
            $parsed['sort'],
            $parsed['direction'],
        );
        $integration = $this->shopify->findOrCreate($userId);

        return view('integration.index', [
            'sendData' => $sendData,
            'integration' => $integration,
            'currentSort' => $parsed['sort'],
            'currentDirection' => $parsed['direction'],
        ]);
    }

    public function scopes(Request $request): View
    {
        $userId = (int) auth()->id();
        $integration = $this->shopify->findOrCreate($userId);
        $enabledScopes = $this->shopify->enabledScopes($integration);
        $scopeCatalog = config('shopify.scopes', []);
        $scopeCatalogJson = collect($scopeCatalog)
            ->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => (string) ($meta['label'] ?? $key),
                'needs_mail_list' => (bool) ($meta['needs_mail_list'] ?? false),
            ])
            ->values()
            ->all();

        $templates = Template::query()
            ->where('status', TemplateStatus::Approved)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $mailLists = MailList::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('integration.shopify-scopes', compact(
            'integration',
            'enabledScopes',
            'scopeCatalog',
            'scopeCatalogJson',
            'templates',
            'mailLists',
        ));
    }

    public function storeDomain(SaveShopifyDomainRequest $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $integration = $this->shopify->saveDomainUrl($userId, $request->validated('domainurl'));

        return response()->json([
            'success' => true,
            'message' => 'Shopify domain saved.',
            'data' => [
                'shopifydomainurl' => $integration->domainUrl(),
            ],
        ]);
    }

    public function getDomain(): JsonResponse
    {
        $integration = $this->shopify->findOrCreate((int) auth()->id());

        if (empty($integration->settings['shopifydomainurl'])) {
            return response()->json(['success' => false, 'message' => 'Shopify domain URL not found.']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'shopifydomainurl' => $integration->domainUrl(),
            ],
        ]);
    }

    public function saveScopes(SaveShopifyScopesRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $userId = (int) auth()->id();

        if (! empty($validated['scope_key'])) {
            if (empty($this->shopify->findOrCreate($userId)->domainUrl())) {
                $message = 'Save your Shopify store domain first before adding webhook scopes.';

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $message], 422)
                    : redirect()->route('integration.shopify.scopes')->with('error', $message);
            }

            $enabled = (bool) ($validated['enabled'] ?? true);
            $templateId = isset($validated['template_id']) ? (string) $validated['template_id'] : null;

            if ($enabled && ! filled($templateId)) {
                $message = 'Select a WhatsApp template for this webhook scope.';

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $message], 422)
                    : redirect()->route('integration.shopify.scopes')->with('error', $message);
            }

            $needsMailList = (bool) (config('shopify.scopes.'.$validated['scope_key'].'.needs_mail_list') ?? false);
            $mailListId = $validated['mail_list_id'] ?? null;
            if ($enabled && $needsMailList && ! filled($mailListId)) {
                $message = 'Select an audience (mail list) for product webhook scopes.';

                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $message], 422)
                    : redirect()->route('integration.shopify.scopes')->with('error', $message);
            }

            $this->shopify->upsertScope(
                $userId,
                (string) $validated['scope_key'],
                $enabled,
                $templateId,
                $mailListId,
            );
        } elseif (! empty($validated['webhookdatascopes'])) {
            $templates = is_array($validated['webhookdatatemplate'] ?? null)
                ? $validated['webhookdatatemplate']
                : [];

            $mailListId = $validated['maillistid'] ?? $validated['mail_list_id'] ?? null;

            $this->shopify->saveScopes(
                $userId,
                $validated['webhookdatascopes'],
                $templates,
                $validated['selectedScope'] ?? null,
                $mailListId,
            );
        } else {
            $message = 'No webhook scope data provided.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : redirect()->route('integration.shopify.scopes')->with('error', $message);
        }

        if ($request->expectsJson()) {
            $integration = $this->shopify->findOrCreate($userId);

            return response()->json([
                'success' => true,
                'message' => 'Shopify webhook scopes updated.',
                'data' => [
                    'enabled_scopes' => $this->shopify->enabledScopes($integration),
                ],
            ]);
        }

        return redirect()->route('integration.shopify.scopes')
            ->with('success', 'Shopify webhook scopes updated.');
    }

    public function removeScope(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'scope_key' => ['required', 'string'],
        ]);

        $userId = (int) auth()->id();
        $this->shopify->removeScope($userId, $validated['scope_key']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Webhook scope removed.',
                'data' => [
                    'enabled_scopes' => $this->shopify->enabledScopes($this->shopify->findOrCreate($userId)),
                ],
            ]);
        }

        return redirect()->route('integration.shopify.scopes')
            ->with('success', 'Webhook scope removed.');
    }

    public function getScopes(): JsonResponse
    {
        $integration = $this->shopify->findOrCreate((int) auth()->id());

        return response()->json([
            'success' => true,
            'data' => $integration->settings ?? [],
            'enabled_scopes' => $this->shopify->enabledScopes($integration),
        ]);
    }

    public function getSendData(Request $request): JsonResponse
    {
        $data = $this->shopify->sendData((int) auth()->id(), 50);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getMailLists(): JsonResponse
    {
        $lists = MailList::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $lists]);
    }
}
