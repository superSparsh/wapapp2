@props([
  'credits' => [],
  'creditsPeriod' => 'daily',
])

@php
  $period = $creditsPeriod ?? ($credits['period'] ?? 'daily');
  $periodLabels = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'yearly' => 'Yearly',
    'all' => 'All time',
  ];
@endphp

<section
  class="flex min-h-[182px] flex-col justify-center gap-4 p-4 pt-0"
  data-dashboard-credits
  data-credits-url="{{ route('dashboard.credits') }}"
>
  <div class="flex flex-wrap items-center gap-4">
    <h2 class="fd-section-title min-w-0 flex-1">Credits Used</h2>
    <button type="button" data-credits-refresh class="fd-btn inline-flex items-center gap-2.5 rounded-lg border border-border bg-elevated px-3 py-2 text-sm font-medium text-text-primary">
      Refresh
      <x-icons.nav-icon name="refresh-2" class="size-4" />
    </button>
    <label class="sr-only" for="credits_period">Sort by period</label>
    <select
      id="credits_period"
      name="credits_period"
      data-credits-period
      data-native-select="true"
      class="min-w-[10.5rem] rounded-lg border border-border bg-elevated py-2 pl-3 pr-8 text-sm font-medium text-text-primary outline-none focus:border-green-500"
    >
      @foreach ($periodLabels as $value => $label)
        <option value="{{ $value }}" @selected($period === $value)>Sort by : {{ $label }}</option>
      @endforeach
    </select>
  </div>

  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <x-ui.credit-stat-card label="Sent" icon="send" data-credit-key="sent" :value="($credits['sent'] ?? 0).'/'.($credits['sent_limit'] ?? 1000)" />
    <x-ui.credit-stat-card label="Marketing Conversations" icon="megaphone" data-credit-key="marketing" :value="($credits['marketing'] ?? 0).'/'.($credits['marketing_limit'] ?? 1000)" />
    <x-ui.credit-stat-card label="Utility Conversations" icon="wrench" data-credit-key="utility" :value="($credits['utility'] ?? 0).'/'.($credits['utility_limit'] ?? 1000)" />
    <x-ui.credit-stat-card label="Services Conversations" icon="headset" data-credit-key="service" :value="($credits['service'] ?? 0).'/'.($credits['service_limit'] ?? 1000)" />
  </div>
</section>
