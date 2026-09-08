<?php

declare(strict_types=1);

namespace App\Domains\Billing\Http\Controllers;

use App\Domains\Account\Services\ActivityLogService;
use App\Domains\Billing\Http\Requests\SaveBillingAddressRequest;
use App\Domains\Billing\Http\Requests\VerifyRazorpayPaymentRequest;
use App\Domains\Billing\Http\Requests\WalletRechargeRequest;
use App\Domains\Billing\Services\BillingAddressService;
use App\Domains\Billing\Services\RazorpayService;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Billing\Services\WalletService;
use App\Enums\RazorpayOrderPurpose;
use App\Http\Controllers\Controller;
use App\Models\RazorpayOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(
        SubscriptionService $subscriptionService,
        WalletService $walletService,
    ): View {
        $rechargeAmount = (float) (session('wallet.recharge_amount')
            ?? config('billing.wallet.default_recharge_amount', 5000));
        $rechargeTotals = $subscriptionService->calculateTotals($rechargeAmount);

        return view('profile.subscription', [
            'summary' => $subscriptionService->subscriptionSummary(),
            'orders' => $subscriptionService->recentOrders(),
            'walletBalance' => $walletService->balance(),
            'walletTransactions' => $walletService->recentTransactions(),
            'rechargeAmount' => $rechargeAmount,
            'rechargeTotals' => $rechargeTotals,
            'razorpayConfigured' => app(RazorpayService::class)->isConfigured(),
        ]);
    }

    public function upgrade(SubscriptionService $subscriptionService): View
    {
        return view('profile.subscription.upgrade', [
            'plans' => $subscriptionService->availablePlans(),
            'currentPlanId' => $subscriptionService->currentPlan()?->id,
        ]);
    }

    public function selectPlan(Request $request, SubscriptionService $subscriptionService): RedirectResponse
    {
        $request->validate(['plan_id' => ['required', 'integer']]);
        $subscriptionService->selectPlan((int) $request->input('plan_id'));

        return redirect()->route('profile.subscription.billing');
    }

    public function billing(BillingAddressService $billingAddressService, SubscriptionService $subscriptionService): View
    {
        return view('profile.subscription.billing', [
            'address' => $billingAddressService->default(),
            'selectedPlan' => $subscriptionService->checkoutPlan(),
        ]);
    }

    public function saveBilling(SaveBillingAddressRequest $request, BillingAddressService $billingAddressService): RedirectResponse
    {
        $billingAddressService->save($request->validated());

        return redirect()
            ->route('profile.subscription.payment')
            ->with('status', 'Billing information saved.');
    }

    public function payment(SubscriptionService $subscriptionService, BillingAddressService $billingAddressService): View
    {
        $selectedPlan = $subscriptionService->checkoutPlan();

        return view('profile.subscription.payment', [
            'selectedPlan' => $selectedPlan,
            'address' => $billingAddressService->default(),
            'totals' => $selectedPlan
                ? $subscriptionService->calculateTotals((float) $selectedPlan->price)
                : null,
            'razorpayKey' => config('billing.razorpay.key'),
            'razorpayConfigured' => app(RazorpayService::class)->isConfigured(),
        ]);
    }

    public function manage(SubscriptionService $subscriptionService): View
    {
        return view('profile.subscription.manage', [
            'summary' => $subscriptionService->subscriptionSummary(),
        ]);
    }

    public function cancel(SubscriptionService $subscriptionService, ActivityLogService $activityLogService): RedirectResponse
    {
        $subscriptionService->cancelSubscription();
        $activityLogService->log('billing.subscription.cancelled');

        return redirect()
            ->route('profile.subscription')
            ->with('status', 'Subscription cancelled.');
    }

    public function checkoutSubscription(
        SubscriptionService $subscriptionService,
    ): JsonResponse|RedirectResponse {
        try {
            $order = $subscriptionService->createSubscriptionOrder();
        } catch (\Throwable $e) {
            if (request()->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        if (request()->expectsJson()) {
            return response()->json([
                'order_id' => $order->razorpay_order_id,
                'amount' => (int) round($order->total_amount * 100),
                'currency' => $order->currency,
                'key' => config('billing.razorpay.key'),
            ]);
        }

        return back()->with('checkout_order_id', $order->razorpay_order_id);
    }

    public function verifyPayment(
        VerifyRazorpayPaymentRequest $request,
        RazorpayService $razorpayService,
        SubscriptionService $subscriptionService,
        WalletService $walletService,
        ActivityLogService $activityLogService,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validated();

        if (! $razorpayService->verifyPaymentSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        )) {
            return $this->paymentError('Invalid payment signature.');
        }

        $order = RazorpayOrder::query()
            ->where('razorpay_order_id', $validated['razorpay_order_id'])
            ->firstOrFail();

        if ($order->purpose === RazorpayOrderPurpose::Subscription) {
            $subscriptionService->completeSubscriptionPayment($order, $validated['razorpay_payment_id']);
            $activityLogService->log('billing.subscription.paid', [
                'subject_type' => RazorpayOrder::class,
                'subject_id' => $order->id,
            ]);
        } else {
            $walletService->completeRecharge($order, $validated['razorpay_payment_id']);
            $activityLogService->log('billing.wallet.recharge', [
                'subject_type' => RazorpayOrder::class,
                'subject_id' => $order->id,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => $request->input('redirect_url')]);
        }

        $redirect = $request->input('redirect_url');

        return redirect()
            ->to(is_string($redirect) && $redirect !== '' ? $redirect : route('profile.subscription'))
            ->with('status', 'Payment completed successfully.');
    }

    public function walletRecharge(
        WalletRechargeRequest $request,
        WalletService $walletService,
    ): JsonResponse|RedirectResponse {
        session(['wallet.recharge_amount' => $request->validated('amount')]);

        try {
            $order = $walletService->createRechargeOrder((float) $request->validated('amount'));
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'order_id' => $order->razorpay_order_id,
                'amount' => (int) round($order->total_amount * 100),
                'currency' => $order->currency,
                'key' => config('billing.razorpay.key'),
            ]);
        }

        return back()->with('checkout_order_id', $order->razorpay_order_id);
    }

    private function paymentError(string $message): RedirectResponse|JsonResponse
    {
        if (request()->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors(['payment' => $message]);
    }
}
