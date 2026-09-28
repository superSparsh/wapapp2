<x-layouts.app title="Drip Statistics - WapApp" active="automation.drip.index">
  <section class="flex flex-col bg-surface lg:flex-row lg:items-stretch">
    <x-automation.drip-flow-canvas :showEditFlow="true" :campaign="$campaign" />

    <div class="min-w-0 flex-1 overflow-y-auto">
      <x-automation.drip-campaign-header :campaign="$campaign" activeTab="statistics">
        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
          <div class="flex flex-col gap-1">
            <h2 class="text-2xl font-bold leading-[1.5] text-text-primary">{{ $campaign->name }}</h2>
            <p class="text-sm font-normal leading-[1.4] text-text-subtle/50">
              Total {{ number_format($metrics['total']) }} automation events
            </p>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-xl font-bold leading-[1.5] text-text-primary">Statistics</h3>
            <div class="flex flex-wrap items-center gap-2">
              <a
                href="{{ route('automation.drip.statistics', $campaign) }}"
                class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-surface"
              >
                <img src="{{ asset('images/automation/refresh-2.svg') }}" alt="" class="size-4" width="16" height="16">
                Refresh
              </a>
              <a
                href="{{ route('automation.drip.statistics.detail', $campaign) }}"
                class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-100"
              >
                View History
              </a>
              <a
                href="{{ route('automation.drip.statistics.export', $campaign) }}"
                class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-100"
              >
                <img src="{{ asset('images/automation/export-csv.svg') }}" alt="" class="size-4" width="16" height="16">
                Export to CSV
              </a>
            </div>
          </div>
        </div>

        <section class="bg-surface p-4 pt-0">
          <div class="rounded-lg bg-elevated p-3">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-2">
              @php
                $detailHref = route('automation.drip.statistics.detail', $campaign);
                $cards = [
                  [
                    'title' => 'Entered',
                    'count' => number_format($metrics['entered']),
                    'total' => number_format($metrics['total']),
                    'percent' => $metrics['entered_pct'],
                    'color' => 'blue',
                    'icon' => 'task',
                  ],
                  [
                    'title' => 'Completed',
                    'count' => number_format($metrics['completed']),
                    'total' => number_format($metrics['total']),
                    'percent' => $metrics['completed_pct'],
                    'color' => 'green',
                    'icon' => 'tick-circle',
                  ],
                  [
                    'title' => 'Dropped',
                    'count' => number_format($metrics['dropped']),
                    'total' => number_format($metrics['total']),
                    'percent' => $metrics['dropped_pct'],
                    'color' => 'orange',
                    'icon' => 'group',
                  ],
                  [
                    'title' => 'Errors',
                    'count' => number_format($metrics['failed']),
                    'total' => number_format($metrics['total']),
                    'percent' => $metrics['failed_pct'],
                    'color' => 'red',
                    'icon' => 'warning',
                  ],
                ];
              @endphp

              @foreach ($cards as $card)
                <x-ui.campaign-metric-card
                  :title="$card['title']"
                  :count="$card['count']"
                  :total="$card['total']"
                  :percent="$card['percent']"
                  :color="$card['color']"
                  :icon="$card['icon']"
                  :details-href="$detailHref"
                  :details-link-color="$card['color'] === 'red' || $card['color'] === 'orange' ? 'text-[#71b56d]' : 'text-green-500'"
                />
              @endforeach
            </div>
          </div>
        </section>

        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
          <h3 class="text-base font-semibold leading-[1.5] text-text-primary">Campaign Performance</h3>

          <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-border bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Total Entered</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-text-primary">{{ $aggregateStats['total_entered'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-border bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Completed</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-green-600">{{ $aggregateStats['total_completed'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-border bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Dropped</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-yellow-600">{{ $aggregateStats['total_dropped'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-border bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Errors</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-red-600">{{ $aggregateStats['total_errors'] ?? 0 }}</p>
            </div>
          </div>

          <div class="rounded-xl border border-border bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <h4 class="text-base font-semibold leading-[1.5] text-text-primary">Completion Rate</h4>
            @php $rate = (float) ($aggregateStats['completion_rate'] ?? 0); @endphp
            <div class="mt-3 flex items-center gap-4">
              <div class="h-4 flex-1 overflow-hidden rounded-full bg-muted-surface">
                <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ min(100, max(0, $rate)) }}%"></div>
              </div>
              <span class="text-lg font-bold text-text-primary">{{ $rate }}%</span>
            </div>
          </div>

          @if (! empty($aggregateStats['top_nodes']))
            <div class="rounded-xl border border-border bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <h4 class="text-base font-semibold leading-[1.5] text-text-primary">Top Nodes by Activity</h4>
              <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left">
                  <thead>
                    <tr class="border-b border-divider">
                      <th class="p-2 text-xs font-medium text-text-muted">Node</th>
                      <th class="p-2 text-xs font-medium text-text-muted">Type</th>
                      <th class="p-2 text-xs font-medium text-text-muted">Entries</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($aggregateStats['top_nodes'] as $node)
                      <tr class="border-t border-divider">
                        <td class="p-2 text-sm text-text-body">{{ $node['node_id'] }}</td>
                        <td class="p-2 text-sm text-text-subtle">{{ $node['node_type'] }}</td>
                        <td class="p-2 text-sm font-semibold text-text-primary">{{ $node['count'] }}</td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          @endif
        </div>
      </x-automation.drip-campaign-header>
    </div>
  </section>

  @push('scripts')
    <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
  @endpush
</x-layouts.app>
