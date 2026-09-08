<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Http\Requests\Shopify\SaveShopifyDomainRequest;
use App\Domains\ThirdParty\Http\Requests\Shopify\SaveShopifyScopesRequest;
use App\Domains\ThirdParty\Services\ShopifyService;
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
        $userId      = (int) auth()->id();
        $sendData    = $this->shopify->sendData($userId, 50);
        $integration = $this->shopify->findOrCreate($userId);

        return view('integration.index', compact('sendData', 'integration'));
    }

    public function scopes(Request $request): View
    {
        $userId      = (int) auth()->id();
        $integration = $this->shopify->findOrCreate($userId);

        return view('integration.shopify-scopes', compact('integration'));
    }

    public function storeDomain(SaveShopifyDomainRequest $request): JsonResponse
    {
        $userId      = (int) auth()->id();
        $integration = $this->shopify->saveDomainUrl($userId, $request->validated('domainurl'));

        return response()->json(['success' => true, 'data' => $integration]);
    }

    public function getDomain(): JsonResponse
    {
        $integration = $this->shopify->findOrCreate((int) auth()->id());

        if (empty($integration->settings['shopifydomainurl'])) {
            return response()->json(['success' => false, 'message' => 'Shopify domain URL not found.']);
        }

        return response()->json(['success' => true, 'data' => $integration]);
    }

    public function saveScopes(SaveShopifyScopesRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId    = (int) auth()->id();

        $scopes = array_filter([
            'access_scope_check'      => $validated['access_scope_check'] ?? null,
            'product_listings_add'    => $validated['product_listings_add'] ?? null,
            'product_listings_remove' => $validated['product_listings_remove'] ?? null,
            'product_listings_update' => $validated['product_listings_update'] ?? null,
            'products_create'         => $validated['products_create'] ?? null,
            'products_delete'         => $validated['products_delete'] ?? null,
            'products_update'         => $validated['products_update'] ?? null,
        ], fn ($v) => $v !== null);

        $this->shopify->saveScopes(
            $userId,
            $scopes,
            [],
            $validated['template_selected'] ?? null,
            $validated['mail_list_id'] ?? null,
        );

        return redirect()->route('integration.shopify.scopes')
            ->with('success', 'Shopify webhook scopes updated.');
    }

    public function getScopes(): JsonResponse
    {
        $integration = $this->shopify->findOrCreate((int) auth()->id());

        return response()->json(['success' => true, 'data' => $integration->settings ?? []]);
    }

    public function getSendData(Request $request): JsonResponse
    {
        $data = $this->shopify->sendData((int) auth()->id(), 50);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getMailLists(): JsonResponse
    {
        // Return mail lists for template association (Eloquent on tenant DB)
        $lists = \DB::table('mail_lists')
            ->where('user_id', auth()->id())
            ->select('id', 'name')
            ->get();

        return response()->json(['success' => true, 'data' => $lists]);
    }
}
