<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Http\Controllers;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Http\Requests\CreatePaymentRequest;
use App\Domains\Commerce\Http\Requests\SavePaymentConfigRequest;
use App\Domains\Commerce\Jobs\SendPaymentLinkJob;
use App\Domains\Commerce\Models\CommerceOrder;
use App\Domains\Commerce\Models\CommercePayment;
use App\Domains\Commerce\Services\CatalogService;
use App\Domains\Commerce\Services\CommerceOrderService;
use App\Domains\Commerce\Services\CommercePaymentService;
use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\WhatsappLine;
use App\Support\ListingSort;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommerceController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
        private readonly CommerceOrderService $orderService,
        private readonly CommercePaymentService $paymentService,
    ) {}

    // ─── Catalog / Products ───────────────────────────────────────────────────

    /**
     * GET /commerce — Products listing for a selected catalog
     */
    public function index(Request $request): View|RedirectResponse
    {
        $line = WhatsappLine::query()->where('is_default', true)->first();
        $catalogId = $request->query('catalog_id');
        $search = trim((string) $request->query('q', ''));

        if ($request->boolean('refresh')) {
            $this->flushCommerceCaches($line, filled($catalogId) ? [(string) $catalogId] : null);

            return redirect()->to($request->fullUrlWithoutQuery(['refresh']));
        }

        $catalogs = [];
        $products = [];
        $error = null;

        if ($line) {
            $catalogResult = $this->catalogService->getCatalogs($line);

            if ($catalogResult['success']) {
                $catalogs = $catalogResult['catalogs'];

                if (! $catalogId && count($catalogs) > 0) {
                    $catalogId = $catalogs[0]['id'];
                }

                if ($catalogId) {
                    $productResult = $this->catalogService->getProducts($line, (string) $catalogId);

                    if ($productResult['success']) {
                        $products = $this->filterProducts($productResult['products'], $search);
                    } else {
                        $error = $productResult['message'];
                    }
                }
            } else {
                $error = $catalogResult['message'];
            }
        } else {
            $error = 'No WhatsApp line configured.';
        }

        return view('commerce.index', [
            'catalogs' => $catalogs,
            'products' => $products,
            'catalogId' => $catalogId,
            'error' => $error,
            'search' => $search,
        ]);
    }

    /**
     * GET /commerce/catalog — Catalogues listing (ListProductCatalog only)
     */
    public function catalogList(Request $request): View|RedirectResponse
    {
        $line = WhatsappLine::query()->where('is_default', true)->first();
        $search = trim((string) $request->query('q', ''));

        if ($request->boolean('refresh')) {
            $this->flushCommerceCaches($line, productCatalogIds: []);

            return redirect()->to($request->fullUrlWithoutQuery(['refresh']));
        }

        $catalogs = [];
        $error = null;

        if ($line) {
            $catalogResult = $this->catalogService->getCatalogs($line);

            if ($catalogResult['success']) {
                $catalogs = $this->filterCatalogs($catalogResult['catalogs'], $search);
            } else {
                $error = $catalogResult['message'];
            }
        } else {
            $error = 'No WhatsApp line configured.';
        }

        return view('commerce.catalog', [
            'catalogs' => $catalogs,
            'error' => $error,
            'search' => $search,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function filterProducts(array $products, string $search): array
    {
        if ($search === '') {
            return $products;
        }

        $needle = mb_strtolower($search);

        return array_values(array_filter(
            $products,
            static function (array $product) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($product['id'] ?? ''),
                    (string) ($product['retailer_id'] ?? ''),
                    (string) ($product['name'] ?? ''),
                    (string) ($product['description'] ?? ''),
                    (string) ($product['brand'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $catalogs
     * @return list<array<string, mixed>>
     */
    private function filterCatalogs(array $catalogs, string $search): array
    {
        if ($search === '') {
            return $catalogs;
        }

        $needle = mb_strtolower($search);

        return array_values(array_filter(
            $catalogs,
            static function (array $catalog) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    (string) ($catalog['id'] ?? ''),
                    (string) ($catalog['name'] ?? ''),
                    (string) ($catalog['business_id'] ?? ''),
                    (string) ($catalog['vertical'] ?? ''),
                ]));

                return str_contains($haystack, $needle);
            },
        ));
    }

    /**
     * @param  list<string>|null  $productCatalogIds  null = flush products for all known catalogs; [] = catalogs only
     */
    private function flushCommerceCaches(?WhatsappLine $line, ?array $productCatalogIds = null): void
    {
        if (! $line) {
            return;
        }

        $this->catalogService->flushCatalogCache($line);

        if ($productCatalogIds === []) {
            return;
        }

        if ($productCatalogIds === null) {
            $catalogResult = $this->catalogService->getCatalogs($line);
            $productCatalogIds = array_map(
                static fn (array $catalog): string => (string) $catalog['id'],
                $catalogResult['catalogs'] ?? [],
            );
        }

        foreach ($productCatalogIds as $id) {
            if ($id !== '') {
                $this->catalogService->flushProductCache($line, $id);
            }
        }
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    /**
     * GET /commerce/orders — Paginated orders list
     */
    public function orders(Request $request): View
    {
        $parsed = ListingSort::fromRequest(
            $request,
            ['id', 'created_at', 'customer_name', 'customer_phone', 'total_price', 'order_status', 'payment_status'],
            'id',
            'desc',
        );
        $orders = $this->orderService->paginate(
            search:        $request->query('q'),
            orderStatus:   $request->query('order_status'),
            paymentStatus: $request->query('payment_status'),
            sort:          $parsed['sort'],
            direction:     $parsed['direction'],
        );

        $stats = $this->orderService->getStats();

        return view('commerce.orders', [
            'orders' => $orders,
            'stats' => $stats,
            'search' => $request->query('q', ''),
            'currentSort' => $parsed['sort'],
            'currentDirection' => $parsed['direction'],
        ]);
    }

    /**
     * GET /commerce/orders/{uuid} — Order detail (JSON, for modal)
     */
    public function orderDetail(string $uuid): JsonResponse
    {
        $order = $this->orderService->findByUuid($uuid);
        $line = WhatsappLine::query()->find($order->whatsapp_line_id)
            ?? WhatsappLine::query()->where('is_default', true)->first();

        if ($line && filled($order->catalog_id)) {
            $productResult = $this->catalogService->getProducts($line, (string) $order->catalog_id);
            if ($productResult['success'] ?? false) {
                $order = $this->orderService->enrichOrderItems($order, $productResult['products'] ?? []);
            }
        }

        return response()->json([
            'id'             => $order->id,
            'uuid'           => $order->uuid,
            'customer_name'  => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'product_items'  => $order->product_items ?? [],
            'total_price'    => $order->total_price,
            'currency'       => $order->currency,
            'order_status'   => $order->order_status->value,
            'order_status_label' => $order->order_status->label(),
            'payment_status' => $order->payment_status->value,
            'payment_status_label' => $order->payment_status->label(),
            'payment_link'   => $order->payment_link,
            'created_at'     => $order->created_at?->format('d/m/Y, g:i:s a'),
            'statuses'       => collect(OrderStatus::cases())->map(fn (OrderStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values(),
        ]);
    }

    /**
     * PATCH /commerce/orders/{uuid}/status — Update order status
     */
    public function updateOrderStatus(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'order_status' => ['required', 'string', Rule::enum(OrderStatus::class)],
        ]);

        $order = $this->orderService->findByUuid($uuid);
        $order = $this->orderService->updateOrderStatus(
            $order,
            OrderStatus::from($validated['order_status']),
        );

        return response()->json([
            'ok' => true,
            'order_status' => $order->order_status->value,
            'order_status_label' => $order->order_status->label(),
        ]);
    }

    // ─── Payment Settings ─────────────────────────────────────────────────────

    /**
     * GET /commerce/settings — Payment dashboard
     */
    public function settings(): View
    {
        $config       = $this->paymentService->getConfig();
        $stats        = $this->paymentService->getStats();
        $payments     = CommercePayment::query()->latest('id')->paginate(25);
        $templates    = Template::query()->select(['id', 'uuid', 'name', 'code'])->orderBy('name')->get();

        return view('commerce.settings', compact('config', 'stats', 'payments', 'templates'));
    }

    /**
     * POST /commerce/settings — Save payment configuration
     */
    public function saveConfig(SavePaymentConfigRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach (['payment_template_id', 'confirmation_template_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $template = PublicId::find(Template::class, $data[$field] ?? null);
                $data[$field] = $template?->id;
            }
        }

        $this->paymentService->saveConfig($data);

        return redirect()->route('commerce.settings')
            ->with('success', 'Payment configuration saved successfully.');
    }

    /**
     * POST /commerce/payments — Create a payment link and dispatch WhatsApp send
     */
    public function createPayment(CreatePaymentRequest $request): RedirectResponse
    {
        try {
            $payment = $this->paymentService->createPaymentLink([
                'customer_name'  => $request->input('customer_name'),
                'customer_phone' => $request->input('customer_phone'),
                'amount'         => $request->validatedAmount(),
                'currency'       => $request->input('currency', 'INR'),
            ]);

            // Dispatch WhatsApp delivery job
            SendPaymentLinkJob::dispatch($payment, (string) tenant('id'));

            return redirect()->route('commerce.settings')
                ->with('success', "Payment link created (#{$payment->internal_order_ref}) and WhatsApp message queued.");
        } catch (\RuntimeException $e) {
            return redirect()->route('commerce.settings')
                ->withErrors(['payment' => $e->getMessage()]);
        }
    }

    /**
     * GET /commerce/payments/callback — Razorpay callback after customer pays (public)
     */
    public function paymentCallback(Request $request): View
    {
        $tenantId = (string) $request->query('tenant', '');
        $initialized = false;

        if ($tenantId !== '' && ! tenancy()->initialized) {
            /** @var Tenant|null $tenant */
            $tenant = tenancy()->central(fn () => Tenant::query()->find($tenantId));

            if ($tenant) {
                tenancy()->initialize($tenant);
                $initialized = true;
            }
        }

        try {
            $paymentLinkId     = $request->query('razorpay_payment_link_id');
            $razorpayPaymentId = $request->query('razorpay_payment_id');
            $status            = $request->query('razorpay_payment_link_status');

            if ($paymentLinkId && $status === 'paid' && $razorpayPaymentId && tenancy()->initialized) {
                $this->paymentService->handleWebhookEvent(
                    'payment_link.paid',
                    (string) $paymentLinkId,
                    (string) $razorpayPaymentId,
                );
            }

            return view('commerce.payment-callback', [
                'status' => $status,
                'authenticated' => auth('web')->check() || auth('team')->check(),
            ]);
        } finally {
            if ($initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * GET /commerce/product-detail — Legacy route alias, redirect to settings
     */
    public function productDetail(): RedirectResponse
    {
        return redirect()->route('commerce.settings');
    }
}
