<x-layouts.app title="Drip Timeline - WapApp" active="automation.drip.index">
  <section class="flex flex-col bg-surface lg:flex-row lg:items-stretch">
    <x-automation.drip-flow-canvas :showEditFlow="true" :campaign="$campaign" />

    <div class="min-w-0 flex-1">
      <x-automation.drip-campaign-header :campaign="$campaign" activeTab="audience">
        <x-automation.audience-view-tabs :campaign="$campaign" active="timeline" />

        <p class="max-w-[388px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Audience' activities recorded for your automated campaign.
        </p>

        <div class="flex flex-col gap-2 bg-surface px-4 pb-4">
          <p class="max-w-[388px] text-xs font-medium leading-[1.4] text-text-body">All Activities</p>

          <div class="flex w-full flex-col items-start">
            @forelse ($activities as $activity)
              <div @class([
                'flex w-full items-center justify-between px-2 py-2.5',
                'bg-muted-surface' => $loop->even,
              ])>
                <div class="flex min-w-0 flex-1 items-center gap-2">
                  <div class="size-8 shrink-0 rounded-full bg-border"></div>
                  <div class="min-w-0 max-w-[245px] flex-1">
                    <p class="text-xs font-semibold leading-[1.5] text-text-subtle">
                      {{ $activity->contact_phone ?? 'Unknown' }}
                    </p>
                    <p class="text-[10px] font-normal leading-[1.5] text-blue-200">
                      <span class="block">
                        @switch($activity->action)
                          @case(\App\Enums\ChatbotFlowStatAction::Entered)
                            Entered flow{{ $activity->node_id ? " at node {$activity->node_id}" : '' }}
                            @break
                          @case(\App\Enums\ChatbotFlowStatAction::Completed)
                            Completed{{ $activity->node_id ? " node {$activity->node_id}" : '' }}
                            @break
                          @case(\App\Enums\ChatbotFlowStatAction::Dropped)
                            Dropped from flow{{ $activity->node_id ? " at node {$activity->node_id}" : '' }}
                            @break
                          @case(\App\Enums\ChatbotFlowStatAction::Error)
                            Error{{ $activity->node_id ? " at node {$activity->node_id}" : '' }}{{ !empty($activity->metadata['message']) ? ': ' . $activity->metadata['message'] : '' }}
                            @break
                        @endswitch
                      </span>
                    </p>
                  </div>
                </div>

                <div class="w-[102px] shrink-0 text-right text-[10px] leading-[1.5]">
                  <p class="font-semibold text-text-subtle">{{ $activity->created_at->diffForHumans() }}</p>
                  <p class="font-normal text-text-body">
                    <span class="text-green-500 underline">{{ $activity->action->value }}</span>
                  </p>
                </div>
              </div>
            @empty
              <p class="w-full py-8 text-center text-sm text-text-body">No activity recorded yet for this campaign.</p>
            @endforelse
          </div>

          @if ($activities->hasPages())
            <x-ui.table-pagination :paginator="$activities" />
          @endif
        </div>
      </x-automation.drip-campaign-header>
    </div>
  </section>
  @push('scripts')
  <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
  @endpush
</x-layouts.app>
