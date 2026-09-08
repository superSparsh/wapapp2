<x-layouts.app title="Audience Overview - WapApp" active="audience.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <x-audience.list-header
        :title="$mailList?->name ?? 'All Lists'"
        :subscribers="(string) ($stats['subscriber_count'] ?? 0)"
      />
      <x-audience.sub-nav active="audience.overview" />
    </div>

    <section class="flex flex-col gap-4 p-4 pt-0">
      <h2 class="fd-section-title text-xl font-semibold">List performance</h2>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-ui.growth-metric-card label="Total Subscribers" :value="(string) $stats['subscriber_count']" badge="{{ $stats['active_percent'] }}%" :up="true" icon="graph" />
        <x-ui.growth-metric-card label="Subscribed" :value="(string) $stats['subscribed_count']" badge="{{ $stats['active_percent'] }}%" :up="true" icon="presentation-chart" />
        <x-ui.growth-metric-card label="Forms" :value="(string) $stats['form_count']" badge="0%" :up="false" icon="personal-card" />
        <x-ui.growth-metric-card label="Total Blacklisted" :value="(string) $stats['blacklisted_count']" badge="0%" :up="false" icon="danger" :danger="true" />
      </div>
    </section>

    <section class="p-4 pt-0">
      <div class="flex flex-col gap-4 rounded-lg border border-border-light bg-elevated p-4">
        <h3 class="text-xl font-semibold leading-[1.4] text-text-body">Subscribers</h3>
        <x-ui.subscribers-chart :series="$subscriberSeries ?? []" />
      </div>
    </section>
  </div>
</x-layouts.app>
