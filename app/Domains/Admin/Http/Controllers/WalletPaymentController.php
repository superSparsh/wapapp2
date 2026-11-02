<?php

declare(strict_types=1);

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Services\BillingAuditService;
use App\Domains\Admin\Services\WalletPaymentJourneyService;
use App\Domains\Admin\Support\AdminListQuery;
use App\Domains\Billing\Jobs\ProcessWalletRazorpayZohoInvoiceJob;
use App\Domains\Billing\Services\RazorpayService;
use App\Domains\Billing\Services\WalletService;
use App\Enums\RazorpayOrderPurpose;
use App\Http\Controllers\Controller;
use App\Models\RazorpayOrder;
use App\Models\RechargeSubscriptionRequest;
use App\Models\Tenant;
use App\Models\ZohoWalletCreditRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class WalletPaymentController extends Controller
{
    public function __construct(
        private readonly WalletPaymentJourneyService $journeys,
        private readonly BillingAuditService $billing,
    ) {}

    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'journeys');
        if (! in_array($tab, ['journeys', 'manual', 'credits', 'zoho'], true)) {
            $tab = 'journeys';
        }

        $tenants = Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company_name']);

        $data = [
            'tab' => $tab,
            'tenants' => $tenants,
            'items' => null,
            'rows' => null,
            'zohoRows' => null,
            'status' => (string) $request->query('status', ''),
            'filters' => [],
            'sortOptions' => [],
            'source' => (string) $request->query('source', ''),
        ];

        if ($tab === 'journeys') {
            $parsed = AdminListQuery::fromRequest(
                $request,
                allowedSorts: ['created_at', 'amount', 'id'],
                defaultSort: 'created_at',
                defaultDirection: 'desc',
            );
            $filters = array_merge($request->only(['q', 'tenant', 'status']), [
                'sort' => $parsed['sort'],
                'direction' => $parsed['direction'],
            ]);
            $report = $this->journeys->listJourneys($filters, (int) $request->integer('page', 1));
            $data = array_merge($data, $report, [
                'sortOptions' => [
                    ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
                    ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
                    ['value' => 'amount', 'label' => 'Highest amount', 'direction' => 'desc'],
                ],
            ]);
        } elseif ($tab === 'manual') {
            $status = (string) $request->query('status', '');
            $parsed = AdminListQuery::fromRequest(
                $request,
                allowedSorts: ['id', 'status', 'amount', 'created_at'],
                defaultSort: 'id',
                defaultDirection: 'desc',
            );
            $query = RechargeSubscriptionRequest::query()
                ->with('tenant:id,name,company_name')
                ->when($status !== '', fn ($builder) => $builder->where('status', $status));

            AdminListQuery::applySearch($query, $parsed['q'], ['tenant_id', 'notes', 'status', 'currency']);
            AdminListQuery::applySort(
                $query,
                $parsed['sort'],
                $parsed['direction'],
                [
                    'id' => 'id',
                    'status' => 'status',
                    'amount' => 'amount',
                    'created_at' => 'created_at',
                ],
                'id',
            );

            $data['rows'] = $query->paginate(20)->withQueryString();
            $data['status'] = $status;
            $data['filters'] = $parsed;
            $data['sortOptions'] = [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'amount', 'label' => 'Amount high–low', 'direction' => 'desc'],
                ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
                ['value' => 'created_at', 'label' => 'Requested', 'direction' => 'desc'],
            ];
        } elseif ($tab === 'credits') {
            $parsed = AdminListQuery::fromRequest(
                $request,
                allowedSorts: ['created_at', 'amount', 'id'],
                defaultSort: 'created_at',
                defaultDirection: 'desc',
            );
            $filters = array_merge($request->only(['q', 'tenant']), [
                'sort' => $parsed['sort'],
                'direction' => $parsed['direction'],
            ]);
            $report = $this->billing->walletRecharges($filters, (int) $request->integer('page', 1));
            $data = array_merge($data, $report, [
                'sortOptions' => [
                    ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
                    ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
                    ['value' => 'amount', 'label' => 'Highest amount', 'direction' => 'desc'],
                    ['value' => 'id', 'label' => 'ID', 'direction' => 'desc'],
                ],
            ]);
        } else {
            $source = (string) $request->query('source', '');
            $parsed = AdminListQuery::fromRequest(
                $request,
                allowedSorts: ['id', 'amount', 'status', 'created_at'],
                defaultSort: 'id',
                defaultDirection: 'desc',
            );
            $query = ZohoWalletCreditRequest::query()
                ->with('tenant:id,name,company_name')
                ->when($source !== '', fn ($builder) => $builder->where('source', $source));

            AdminListQuery::applySearch($query, $parsed['q'], [
                'tenant_id',
                'external_id',
                'invoice_number',
                'status',
                'source',
            ]);
            AdminListQuery::applySort(
                $query,
                $parsed['sort'],
                $parsed['direction'],
                [
                    'id' => 'id',
                    'amount' => 'amount',
                    'status' => 'status',
                    'created_at' => 'created_at',
                ],
                'id',
            );

            $data['zohoRows'] = $query->paginate(20)->withQueryString();
            $data['source'] = $source;
            $data['filters'] = $parsed;
            $data['sortOptions'] = [
                ['value' => 'id', 'label' => 'Newest first', 'direction' => 'desc'],
                ['value' => 'amount', 'label' => 'Amount high–low', 'direction' => 'desc'],
                ['value' => 'status', 'label' => 'Status', 'direction' => 'asc'],
                ['value' => 'created_at', 'label' => 'Received', 'direction' => 'desc'],
            ];
        }

        return view('admin.wallet-payments.index', $data);
    }

    public function show(string $tenant, string $order): View|RedirectResponse
    {
        try {
            $detail = $this->journeys->detail($tenant, $order);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.wallet-payments.index')
                ->with('error', $e->getMessage());
        }

        return view('admin.wallet-payments.show', [
            'detail' => $detail,
        ]);
    }

    public function completeFromRazorpay(string $tenant, string $order): RedirectResponse
    {
        $tenantModel = Tenant::query()->find($tenant);
        if ($tenantModel === null) {
            return back()->with('error', 'Customer not found.');
        }

        try {
            tenancy()->initialize($tenantModel);

            /** @var RazorpayOrder|null $razorpayOrder */
            $razorpayOrder = RazorpayOrder::query()
                ->where('uuid', $order)
                ->where('purpose', RazorpayOrderPurpose::WalletRecharge)
                ->first();

            if ($razorpayOrder === null) {
                return back()->with('error', 'Wallet payment order not found.');
            }

            $razorpay = app(RazorpayService::class);
            $paymentId = $razorpay->findCapturedPaymentIdForOrder((string) $razorpayOrder->razorpay_order_id);
            if ($paymentId === null) {
                return back()->with('error', 'No captured Razorpay payment found for this order yet.');
            }

            $razorpay->assertPaymentMatchesOrder($razorpayOrder, $paymentId);
            app(WalletService::class)->completeRecharge($razorpayOrder, $paymentId);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Unable to complete from Razorpay: '.$e->getMessage());
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return back()->with('status', 'Payment verified. Wallet credited and Zoho sync queued.');
    }

    public function retryZoho(string $tenant, string $order): RedirectResponse
    {
        $tenantModel = Tenant::query()->find($tenant);
        if ($tenantModel === null) {
            return back()->with('error', 'Customer not found.');
        }

        try {
            tenancy()->initialize($tenantModel);

            /** @var RazorpayOrder|null $razorpayOrder */
            $razorpayOrder = RazorpayOrder::query()
                ->where('uuid', $order)
                ->where('purpose', RazorpayOrderPurpose::WalletRecharge)
                ->first();

            if ($razorpayOrder === null) {
                return back()->with('error', 'Wallet payment order not found.');
            }

            $paymentId = (string) ($razorpayOrder->razorpay_payment_id ?? '');
            if ($paymentId === '') {
                return back()->with('error', 'Order has no Razorpay payment id yet. Complete from Razorpay first.');
            }

            ProcessWalletRazorpayZohoInvoiceJob::dispatch(
                (string) $tenantModel->id,
                (int) $razorpayOrder->id,
                $paymentId,
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Unable to queue Zoho retry: '.$e->getMessage());
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return back()->with('status', 'Zoho invoice sync queued. Refresh in a minute.');
    }
}
