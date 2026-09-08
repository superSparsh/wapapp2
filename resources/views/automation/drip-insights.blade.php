<x-layouts.app title="Drip Insights - WapApp" active="automation.drip.index">
  <section data-drip-workspace class="flex flex-col bg-surface lg:flex-row lg:items-stretch">
    <x-automation.drip-flow-canvas :showMaximize="true" :showEditFlow="true" :campaign="$campaign" />

    <div data-drip-side-panel class="min-w-0 flex-1">
      <x-automation.drip-campaign-header :campaign="$campaign" activeTab="insights">
        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
          <div class="flex items-start justify-between gap-3 text-sm font-normal leading-[1.4] whitespace-nowrap">
            <span class="text-text-subtle">Your automation stats overview</span>
            <span class="text-primary-2">Started: {{ $campaign->created_at->diffForHumans() }}</span>
          </div>

          <div class="flex items-center rounded-lg border border-dashed border-border bg-blue-50 py-1">
            @php
              $overviewStats = [
                [$overview['total_contacts'], 'Contacts'],
                [$overview['involved'], 'Involved'],
                [$overview['completion_pct'], '% complete'],
              ];
            @endphp
            @foreach ($overviewStats as $index => [$value, $label])
              @if ($index > 0)
                <img
                  src="{{ asset('images/automation/stats-divider-v.svg') }}"
                  alt=""
                  class="h-[60px] w-px shrink-0 self-center"
                  width="1"
                  height="60"
                >
              @endif
              <div class="flex min-w-0 flex-1 flex-col items-center justify-center gap-0.5 p-2 text-center text-text-body">
                <p class="text-xl font-medium leading-[1.5] whitespace-nowrap">{{ $value }}</p>
                <p class="text-sm font-medium leading-[1.5] whitespace-nowrap opacity-80">{{ $label }}</p>
              </div>
            @endforeach
          </div>

          <p class="w-full text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            Below is the insight of your campaign performance and how users respond to your campaign
          </p>

          <div class="flex w-full flex-col items-start">
            @forelse ($steps as $step)
              <div @class([
                'flex w-full items-center justify-between px-2 py-2.5',
                'bg-muted-surface' => $loop->even,
              ])>
                <div class="flex w-[195px] shrink-0 items-center gap-2">
                  <div class="size-8 shrink-0 rounded-full bg-border"></div>
                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold leading-[1.5] text-text-subtle">
                      <span class="block">{{ $step['node_type'] }}</span>
                      <span class="block text-[10px] opacity-60">{{ $step['node_id'] }}</span>
                    </p>
                    <p class="text-[10px] font-normal leading-[1.5] text-blue-200">{{ $step['triggered'] }} triggered</p>
                  </div>
                </div>

                <div class="w-[87px] shrink-0 text-[10px] leading-[1.5]">
                  <p class="font-semibold text-text-subtle">{{ $step['last_updated'] }}</p>
                  <p class="font-normal text-text-body">Last updated</p>
                </div>

                <div class="w-[65px] shrink-0">
                  <p class="text-xl font-semibold leading-[1.5] text-text-subtle">{{ $step['rate'] }}</p>
                </div>
              </div>
            @empty
              <p class="w-full py-8 text-center text-sm text-text-body">No performance data yet. Campaign needs to be triggered.</p>
            @endforelse
          </div>
        </div>
      </x-automation.drip-campaign-header>
    </div>
  </section>
  @push('scripts')
  <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
  @endpush
</x-layouts.app>
