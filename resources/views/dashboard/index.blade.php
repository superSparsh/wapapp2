@php
  $closeHref = request()->url();
@endphp

<x-layouts.app title="Dashboard - WapApp" active="dashboard">
  <div class="flex flex-col">
    @if (session('status') || request('status') === 'wallet-recharged')
      <div class="mx-4 mt-4 rounded-lg bg-green-50 p-3 text-sm text-primary-2">
        {{ session('status', 'Wallet recharged successfully.') }}
      </div>
    @endif

    <x-dashboard.page-header
      :title="$greeting"
      subtitle="Welcome back! Here's your business overview."
    />

    @include('dashboard.partials.credits-used', [
      'credits' => $credits,
      'creditsPeriod' => $creditsPeriod ?? 'daily',
    ])
    @include('dashboard.partials.account-wallet', [
      'walletBalance' => $walletBalance,
      'subscription' => $subscription,
    ])
    @include('dashboard.partials.campaign-review')
    @include('dashboard.partials.list-growth-metrics')

    <x-dashboard.recharge-modal
      :show="request('modal') === 'recharge'"
      :close-href="$closeHref"
      :amount="$rechargeAmount"
      :quick-amounts="$quickAmounts"
      :recharge-totals="$rechargeTotals"
      :razorpay-configured="$razorpayConfigured"
    />
    <x-dashboard.resend-failed-modal :show="request('modal') === 'resend-failed'" :close-href="$closeHref" />
  </div>
</x-layouts.app>
