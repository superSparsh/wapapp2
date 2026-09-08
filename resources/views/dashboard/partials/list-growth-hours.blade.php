<section class="flex flex-col gap-4 bg-surface p-4">
  <div class="flex flex-wrap items-center gap-4">
    <h2 class="fd-section-title min-w-0 flex-1">List Growth</h2>
    <div class="flex w-[200px] items-center justify-between gap-2.5 rounded border border-border-light bg-elevated px-3 py-2.5">
      <span class="fd-filter-label text-primary-2">All</span>
      <x-icons.nav-icon name="arrow-down" class="size-4" />
    </div>
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-lg border border-[0.5px] border-border-light bg-elevated p-4">
      <h3 class="mb-4 text-sm font-semibold leading-[1.4] text-text-body" style="font-family: var(--font-display)">Hours Spent</h3>
      <p class="mb-3 text-xs font-semibold leading-[1.4] text-text-body" style="font-family: var(--font-logo)">Subscriber's</p>
      <x-ui.hours-spent-chart />
    </div>
    <div class="rounded-lg border border-[0.5px] border-border-light bg-elevated p-4">
      <h3 class="mb-4 text-sm font-semibold leading-[1.4] text-text-body" style="font-family: var(--font-display)">Hours Spent</h3>
      <x-ui.hours-spent-donut />
    </div>
  </div>
</section>
