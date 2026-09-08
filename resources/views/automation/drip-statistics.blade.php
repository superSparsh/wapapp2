<x-layouts.app title="Drip Statistics - WapApp" active="automation.drip.index" mainOverflow="overflow-hidden">
  <section class="flex h-full min-h-0 flex-col bg-surface lg:flex-row lg:items-stretch">
    <x-automation.drip-flow-canvas :showMaximize="true" :showEditFlow="true" :campaign="$campaign" />

    <div class="min-w-0 flex-1 overflow-y-auto">
      <x-automation.drip-campaign-header :campaign="$campaign" activeTab="statistics">
        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
          <div class="flex w-full flex-col gap-2">
            <h2 class="text-xl font-bold leading-[1.5] text-text-primary">
              {{ $campaign->name }}
            </h2>
            <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
              Statistics overview for your drip campaign performance.
            </p>
            <p class="text-xl font-medium leading-[1.5] text-text-primary">
              Total {{ $metrics['total'] }} Messages
            </p>
          </div>

          <div class="flex items-center justify-end gap-2">
            <h3 class="min-w-0 flex-1 text-lg font-bold leading-[1.5] text-text-primary">Statistics</h3>
            <a
              href="{{ route('automation.drip.statistics', $campaign) }}"
              class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-solid border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-surface"
            >
              <img src="{{ asset('images/automation/refresh-2.svg') }}" alt="" class="size-4" width="16" height="16">
              Refresh
            </a>
          </div>
        </div>

        <section class="grid grid-cols-1 gap-4 bg-surface p-4 pt-0 sm:grid-cols-2 xl:grid-cols-4">
          <x-automation.stat-gauge
            label="Sent"
            :percent="$metrics['sent_pct']"
            :count="$metrics['sent'] . ' / ' . $metrics['total']"
            percentColor="#9ca3af"
            bgColor="#f1f1f2"
            arc="gauge-sent-arc.svg"
            :historyHref="route('automation.drip.statistics.detail', $campaign)"
          />
          <x-automation.stat-gauge
            label="Read"
            :percent="$metrics['read_pct']"
            :count="$metrics['read'] . ' / ' . $metrics['total']"
            percentColor="#10b981"
            bgColor="#e3f3ee"
            arc="gauge-read-fill.svg"
            :historyHref="route('automation.drip.statistics.detail', $campaign)"
          />
          <x-automation.stat-gauge
            label="Delivered"
            :percent="$metrics['delivered_pct']"
            :count="$metrics['delivered'] . ' / ' . $metrics['total']"
            percentColor="#3b82f6"
            bgColor="#e7eefa"
            arc="gauge-delivered-arc.svg"
            :historyHref="route('automation.drip.statistics.detail', $campaign)"
          />
          <x-automation.stat-gauge
            label="Failed"
            :percent="$metrics['failed_pct']"
            :count="$metrics['failed'] . ' / ' . $metrics['total']"
            percentColor="#ef4444"
            bgColor="#f9e8e8"
            :failed="true"
            :historyHref="route('automation.drip.statistics.detail', $campaign)"
          />
        </section>

        {{-- Aggregate Statistics (like legacy chatbot stats) --}}
        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
          <h3 class="text-base font-semibold leading-[1.5] text-text-primary">Campaign Performance</h3>

          <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Total Entered</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-text-primary">{{ $aggregateStats['total_entered'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Completed</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-green-600">{{ $aggregateStats['total_completed'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Dropped</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-yellow-600">{{ $aggregateStats['total_dropped'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
              <p class="text-xs font-medium leading-[1.5] text-text-muted">Errors</p>
              <p class="mt-1 text-2xl font-bold leading-[1.5] text-red-600">{{ $aggregateStats['total_errors'] ?? 0 }}</p>
            </div>
          </div>

          <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
            <h4 class="text-base font-semibold leading-[1.5] text-text-primary">Completion Rate</h4>
            @php
              $rate = $aggregateStats['completion_rate'] ?? 0;
            @endphp
            <div class="mt-3 flex items-center gap-4">
              <div class="h-4 flex-1 overflow-hidden rounded-full bg-muted-surface">
                <div class="h-full rounded-full bg-green-500" style="width: {{ $rate }}%"></div>
              </div>
              <span class="text-lg font-bold text-text-primary">{{ $rate }}%</span>
            </div>
          </div>

          @if (!empty($aggregateStats['top_nodes']))
            <div class="rounded-xl bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
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
