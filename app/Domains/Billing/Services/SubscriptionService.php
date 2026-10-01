<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services;

use App\Enums\RazorpayOrderPurpose;
use App\Enums\RazorpayOrderStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Plan;
use App\Models\RazorpayOrder;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubscriptionService
{
    public function __construct(
        private readonly RazorpayService $razorpayService,
        private readonly WalletService $walletService,
    ) {}

    /** @return Collection<int, Plan> */
    public function availablePlans(): Collection
    {
        return tenancy()->central(fn () => Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get());
    }

    public function currentPlan(): ?Plan
    {
        $tenant = $this->currentTenant();
        $subscription = $this->activeSubscription();

        $planId = $tenant?->plan_id ?: $subscription?->plan_id;
        if (! $planId) {
            return null;
        }

        return tenancy()->central(fn () => Plan::query()->find($planId));
    }

    public function activeSubscription(): ?Subscription
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->latest('id')
            ->first();
    }

    public function subscriptionSummary(): array
    {
        $subscription = $this->activeSubscription();
        $plan = $this->currentPlan();
        $tenant = $this->currentTenant();

        // Always re-read central tenant so admin Extend-validity settings are fresh.
        if ($tenant !== null) {
            $fresh = tenancy()->central(fn () => Tenant::query()->find($tenant->id));
            if ($fresh !== null) {
                $tenant = $fresh;
            }
        }

        // Fallback plan name from subscription metadata when central plan row is missing.
        $planName = $plan?->name;
        if ($planName === null && is_array($subscription?->metadata ?? null)) {
            $metaName = $subscription->metadata['plan_name'] ?? $subscription->metadata['name'] ?? null;
            $planName = is_string($metaName) && $metaName !== '' ? $metaName : null;
        }

        // Account validity resolution (later date wins when multiple sources exist).
        $candidates = [];

        if ($subscription?->ends_at instanceof Carbon) {
            $candidates[] = $subscription->ends_at->copy();
        }

        $settingsUntil = $this->tenantValidUntil($tenant);
        if ($settingsUntil instanceof Carbon) {
            $candidates[] = $settingsUntil;
        }

        // Latest subscription row (even if not Active) may still carry ends_at from migration.
        if ($subscription === null) {
            $latest = Subscription::query()->latest('id')->first();
            if ($latest?->ends_at instanceof Carbon) {
                $candidates[] = $latest->ends_at->copy();
            }
            if ($subscription === null && $latest !== null) {
                $subscription = $latest;
                if ($planName === null && is_array($latest->metadata ?? null)) {
                    $metaName = $latest->metadata['plan_name'] ?? $latest->metadata['name'] ?? null;
                    $planName = is_string($metaName) && $metaName !== '' ? $metaName : null;
                }
            }
        }

        // Derive from plan validity when ends_at was never stored.
        $validityDays = $plan instanceof Plan ? $plan->resolvedValidityDays() : 0;
        if ($validityDays > 0) {
            $start = $subscription?->starts_at instanceof Carbon
                ? $subscription->starts_at->copy()
                : null;
            if ($start === null && $tenant?->provisioned_at) {
                try {
                    $start = Carbon::parse((string) $tenant->provisioned_at);
                } catch (Throwable) {
                    $start = null;
                }
            }
            if ($start === null && $tenant?->created_at) {
                $start = $tenant->created_at->copy();
            }
            if ($start instanceof Carbon) {
                $candidates[] = $start->copy()->addDays($validityDays)->endOfDay();
            }
        }

        $expiresAt = null;
        foreach ($candidates as $candidate) {
            if ($expiresAt === null || $candidate->greaterThan($expiresAt)) {
                $expiresAt = $candidate;
            }
        }

        return [
            'plan' => $plan,
            'plan_name' => $planName,
            'subscription' => $subscription,
            'expires_at' => $expiresAt,
            'is_cancelled' => $subscription?->status === SubscriptionStatus::Cancelled,
        ];
    }

    public function selectPlan(int $planId): Plan
    {
        $plan = tenancy()->central(fn () => Plan::query()
            ->where('is_active', true)
            ->findOrFail($planId));

        session(['checkout.plan_id' => $plan->id]);

        return $plan;
    }

    public function checkoutPlanId(): ?int
    {
        return session('checkout.plan_id');
    }

    public function checkoutPlan(): ?Plan
    {
        $planId = $this->checkoutPlanId();

        if (! $planId) {
            return null;
        }

        return tenancy()->central(fn () => Plan::query()->find($planId));
    }

    public function calculateTotals(float $amount): array
    {
        $gstRate = (float) config('billing.gst_rate', 18);
        $tax = round($amount * ($gstRate / 100), 2);

        return [
            'amount' => $amount,
            'tax' => $tax,
            'total' => round($amount + $tax, 2),
            'gst_rate' => $gstRate,
        ];
    }

    public function createSubscriptionOrder(?int $planId = null): RazorpayOrder
    {
        $planId ??= $this->checkoutPlanId();
        abort_unless($planId, 422, 'Select a plan before checkout.');

        $plan = tenancy()->central(fn () => Plan::query()->findOrFail($planId));
        $totals = $this->calculateTotals((float) $plan->price);

        return DB::transaction(function () use ($plan, $totals): RazorpayOrder {
            $razorpayOrder = $this->razorpayService->createOrder(
                amount: $totals['total'],
                currency: $plan->currency,
                purpose: RazorpayOrderPurpose::Subscription,
                notes: ['plan_id' => $plan->id],
            );

            return RazorpayOrder::query()->create([
                'razorpay_order_id' => $razorpayOrder['id'],
                'purpose' => RazorpayOrderPurpose::Subscription,
                'amount' => $totals['amount'],
                'tax_amount' => $totals['tax'],
                'total_amount' => $totals['total'],
                'currency' => $plan->currency,
                'status' => RazorpayOrderStatus::Created,
                'plan_id' => $plan->id,
                'metadata' => ['razorpay' => $razorpayOrder],
            ]);
        });
    }

    public function completeSubscriptionPayment(RazorpayOrder $order, string $paymentId): void
    {
        DB::transaction(function () use ($order, $paymentId): void {
            $this->razorpayService->markOrderPaid($order, $paymentId);

            $plan = tenancy()->central(fn () => Plan::query()->findOrFail($order->plan_id));
            $endsAt = now()->addDays($plan->resolvedValidityDays());

            Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancelled_at' => now(),
                ]);

            Subscription::query()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);

            $startingWallet = (float) ($plan->starting_wallet_balance ?? 0);
            if ($startingWallet > 0) {
                $this->walletService->adminCredit(
                    $startingWallet,
                    'Starting wallet balance for plan: '.$plan->name,
                );
            }

            $tenant = $this->currentTenant();
            if ($tenant) {
                tenancy()->central(function () use ($tenant, $plan, $endsAt): void {
                    $settings = is_array($tenant->settings) ? $tenant->settings : [];
                    $settings['valid_until'] = $endsAt->toDateString();

                    Tenant::query()->whereKey($tenant->id)->update([
                        'plan_id' => $plan->id,
                        'settings' => $settings,
                    ]);
                });
            }

            session()->forget('checkout.plan_id');
        });
    }

    public function cancelSubscription(): void
    {
        $subscription = $this->activeSubscription();
        abort_unless($subscription, 404, 'No active subscription found.');

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /** @return Collection<int, RazorpayOrder> */
    public function recentOrders(int $limit = 20): Collection
    {
        return RazorpayOrder::query()->latest('id')->limit($limit)->get();
    }

    private function currentTenant(): ?Tenant
    {
        $tenantId = tenant('id');

        if (! $tenantId) {
            return null;
        }

        return tenancy()->central(fn () => Tenant::query()->find($tenantId));
    }

    /**
     * Admin "Extend validity" stores expiry on the central tenant settings row.
     */
    private function tenantValidUntil(?Tenant $tenant): ?Carbon
    {
        if ($tenant === null) {
            return null;
        }

        $raw = is_array($tenant->settings) ? ($tenant->settings['valid_until'] ?? null) : null;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
