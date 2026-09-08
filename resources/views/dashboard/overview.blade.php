@php
  $closeHref = request()->url();
@endphp

<x-layouts.app title="Dashboard Performance - WapApp" active="dashboard">
  <div class="flex flex-col">
    <x-dashboard.page-header
      title="Good Afternoon,Info Team"
      subtitle="Welcome back! Here's your loan business overview."
    />

    @include('dashboard.partials.credits-used')
    @include('dashboard.partials.account-wallet')
    @include('dashboard.partials.list-growth-hours')

    <x-dashboard.recharge-modal :show="request('modal') === 'recharge'" :close-href="$closeHref" />
  </div>
</x-layouts.app>
