<?php

declare(strict_types=1);

namespace App\Domains\ThirdParty\Http\Controllers;

use App\Domains\ThirdParty\Models\WooCommerceStore;
use App\Domains\ThirdParty\Services\WooCommerceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WooCommerceController extends Controller
{
    public function __construct(
        private readonly WooCommerceService $service,
    ) {}

    public function index(): View
    {
        return view('integration.woocommerce', [
            'stores' => $this->service->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'store_url' => ['required', 'url', 'max:500'],
            'consumer_key' => ['nullable', 'string', 'max:255'],
            'consumer_secret' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $store = $this->service->store($validated);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'store' => $store], 201);
        }

        return back()->with('status', 'WooCommerce store connected.');
    }

    public function destroy(WooCommerceStore $wooCommerceStore): RedirectResponse|JsonResponse
    {
        $this->service->destroy($wooCommerceStore);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('status', 'WooCommerce store removed.');
    }
}
