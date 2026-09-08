@php
  $totals = $totals ?? null;
@endphp

<x-profile.layout title="Payment Method - WapApp" headerTitle="Subscription" active="profile.subscription">
  <div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Payment</h1>
      <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
        Complete your subscription payment with Razorpay.
      </p>
    </div>

    <nav class="flex flex-wrap items-center gap-5" aria-label="Subscription steps">
      <a href="{{ route('profile.subscription.upgrade') }}" class="text-xl font-semibold text-text-primary">Select Plans</a>
      <img src="{{ asset('images/profile/chevron-right.svg') }}" alt="" class="size-6 shrink-0">
      <a href="{{ route('profile.subscription.billing') }}" class="text-xl font-semibold text-text-primary">Billing Information</a>
      <img src="{{ asset('images/profile/chevron-right.svg') }}" alt="" class="size-6 shrink-0">
      <span class="text-xl font-semibold text-text-primary">Payment</span>
    </nav>
  </div>

  <section class="flex flex-col gap-4 p-4 pt-0 xl:flex-row xl:items-start">
    <div class="flex w-full flex-col gap-4 rounded-lg bg-elevated p-4 xl:max-w-[746px]">
      <div class="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 p-4">
        <img src="{{ asset('images/profile/razorpay.png') }}" alt="Razorpay" class="size-10">
        <div>
          <p class="text-base font-semibold text-text-primary">Razorpay</p>
          <p class="text-sm text-text-muted">Netbanking, UPI, Credit & Debit Cards</p>
        </div>
      </div>

      @error('payment')
        <p class="text-sm text-[red]">{{ $message }}</p>
      @enderror

      @unless ($selectedPlan)
        <p class="text-sm text-text-muted">Select a plan first.</p>
        <a href="{{ route('profile.subscription.upgrade') }}" class="text-sm font-semibold text-green-500 underline">Choose plan</a>
      @endunless
    </div>

    @if ($selectedPlan && $totals)
      <aside class="flex min-w-0 flex-1 flex-col gap-4 rounded-lg bg-elevated p-4">
        <h2 class="text-2xl font-bold text-text-primary">Your Order</h2>
        <p class="text-sm text-text-subtle">Plan: {{ $selectedPlan->name }}</p>

        <div class="rounded-xl border border-border-light bg-muted-surface p-4 text-sm">
          <div class="flex justify-between"><span class="text-text-muted">{{ $selectedPlan->name }}</span><span>₹ {{ number_format($totals['amount'], 2) }}</span></div>
          <div class="mt-2 flex justify-between"><span class="text-text-muted">GST ({{ $totals['gst_rate'] }}%)</span><span>₹ {{ number_format($totals['tax'], 2) }}</span></div>
          <div class="mt-4 border-t border-dashed border-border pt-4 flex justify-between font-semibold text-green-700">
            <span>Total</span><span>₹ {{ number_format($totals['total'], 2) }}</span>
          </div>
        </div>

        <x-billing.razorpay-checkout
          :checkout-url="route('profile.subscription.checkout')"
          :verify-url="route('profile.subscription.verify-payment')"
          :button-label="'Pay ₹ '.number_format($totals['total'], 2).' Securely'"
          :razorpay-configured="$razorpayConfigured"
        />
      </aside>
    @endif
  </section>
</x-profile.layout>
