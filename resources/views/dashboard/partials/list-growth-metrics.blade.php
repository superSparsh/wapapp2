@php
  $m = $growthMetrics ?? ['subscribed' => 0, 'unsubscribed' => 0, 'blacklisted' => 0, 'subscribeRate' => '0.00%', 'unsubscribeRate' => '0.00%'];
  $subscriberSeries = $subscriberSeries ?? [];
@endphp
<section class="flex flex-col gap-4 bg-surface p-4">
  <div class="flex flex-wrap items-center gap-4">
    <h2 class="fd-section-title min-w-0 flex-1">List Growth</h2>
  </div>

  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <x-ui.growth-metric-card label="Avg subscribe rate" :value="(string) $m['subscribed']" :badge="$m['subscribeRate']" :up="true" icon="graph" />
    <x-ui.growth-metric-card label="Avg unsubscribe rate" :value="(string) $m['unsubscribed']" :badge="$m['unsubscribeRate']" :up="false" icon="presentation-chart" />
    <x-ui.growth-metric-card label="Total unsubscribers" :value="(string) $m['unsubscribed']" :badge="$m['unsubscribeRate']" :up="false" icon="personal-card" />
    <x-ui.growth-metric-card label="Total Blacklisted" :value="(string) $m['blacklisted']" badge="" :up="false" icon="danger" :danger="$m['blacklisted'] > 0" />
  </div>

  <div class="rounded-lg border border-[0.5px] border-border-light bg-elevated p-4">
    <h3 class="fd-section-title mb-4 text-xl font-semibold">Subscribers</h3>
    <x-ui.subscribers-chart :series="$subscriberSeries" />
  </div>
</section>
