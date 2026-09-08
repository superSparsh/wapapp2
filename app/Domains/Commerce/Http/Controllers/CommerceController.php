<?php

declare(strict_types=1);

namespace App\Domains\Commerce\Http\Controllers;

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
use App\Models\WhatsappLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * GET /commerce — Catalog + Products listing
     */
    public function index(Request $request): View
    {
        $line      = WhatsappLine::query()->where('is_default', true)->first();
        $catalogs  = [];
        $products  = [];
        $catalogId = $request->query('catalog_id');
        $error     = null;

        if ($line) {
            $catalogResult = $this->catalogService->getCatalogs($line);

            if ($catalogResult['success']) {
                $catalogs = $catalogResult['catalogs'];

                // Default to first catalog if no explicit selection
                if (! $catalogId && count($catalogs) > 0) {
                    $catalogId = $catalogs[0]['id'];
                }

                if ($catalogId) {
                    $productResult = $this->catalogService->getProducts($line, (string) $catalogId);

                    if ($productResult['success']) {
                        $products = $productResult['products'];
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

        return view('commerce.index', compact('catalogs', 'products', 'catalogId', 'error'));
    }

    /**
     * GET /commerce/catalog — Alias: delegates to index (catalog + products)
     */
    public function catalogList(Request $request): View
    {
        return $this->index($request);
    }

    // ─── Orders ───────────────────────────────────────────────────────────────

    /**
     * GET /commerce/orders — Paginated orders list
     */
    public function orders(Request $request): View
    {
        $orders = $this->orderService->paginate(
            search:        $request->query('q'),
            orderStatus:   $request->query('order_status'),
            paymentStatus: $request->query('payment_status'),
        );

        $stats = $this->orderService->getStats();

        return view('commerce.orders', compact('orders', 'stats'));
    }

    /**
     * GET /commerce/orders/{uuid} — Order detail (JSON, for modal)
     */
    public function orderDetail(string $uuid): JsonResponse
    {
        $order = CommerceOrder::query()->where('uuid', $uuid)->firstOrFail();

        return response()->json([
            'id'             => $order->id,
            'uuid'           => $order->uuid,
            'customer_name'  => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'product_items'  => $order->product_items ?? [],
            'total_price'    => $order->total_price,
            'currency'       => $order->currency,
            'order_status'   => $order->order_status->value,
            'payment_status' => $order->payment_status->value,
            'payment_link'   => $order->payment_link,
            'created_at'     => $order->created_at?->format('d/m/Y, g:i:s a'),
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
        $templates    = Template::query()->select(['id', 'name', 'code'])->orderBy('name')->get();

        return view('commerce.settings', compact('config', 'stats', 'payments', 'templates'));
    }

    /**
     * POST /commerce/settings — Save payment configuration
     */
    public function saveConfig(SavePaymentConfigRequest $request): RedirectResponse
    {
        $this->paymentService->saveConfig($request->validated());

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
     * GET /commerce/payments/callback — Razorpay callback after customer pays
     */
    public function paymentCallback(Request $request): View
    {
        $paymentLinkId    = $request->query('razorpay_payment_link_id');
        $razorpayPaymentId = $request->query('razorpay_payment_id');
        $status           = $request->query('razorpay_payment_link_status');

        if ($paymentLinkId && $status === 'paid' && $razorpayPaymentId) {
            $this->paymentService->handleWebhookEvent('payment_link.paid', (string) $paymentLinkId, (string) $razorpayPaymentId);
        }

        return view('commerce.payment-callback', compact('status'));
    }

    /**
     * GET /commerce/product-detail — Legacy route alias, redirect to settings
     */
    public function productDetail(): RedirectResponse
    {
        return redirect()->route('commerce.settings');
    }
}
