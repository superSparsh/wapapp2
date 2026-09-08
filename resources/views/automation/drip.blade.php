<x-layouts.app title="Drip Marketing - WapApp" active="automation.drip.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">Drip Marketing</h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Create automated drip sequences that send messages to your audience over time based on triggers and delays.
        </p>
      </div>

      <x-ui.listing-toolbar
        :action="route('automation.drip.index')"
        :search-value="request('search')"
        :current-sort="$currentSort ?? request('sort', 'created_at')"
        :current-direction="$currentDirection ?? request('direction', 'desc')"
        :sort-options="[
          ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
          ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
          ['value' => 'stats_count', 'label' => 'Most activity', 'direction' => 'desc'],
        ]"
      >
        <x-slot:actions>
          <a
            href="{{ route('automation.drip.create') }}"
            class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold leading-[1.5] text-primary-2 transition-opacity hover:opacity-90"
          >
            <img src="{{ asset('images/automation/add.svg') }}" alt="" class="size-5" width="20" height="20">
            Add New
          </a>
        </x-slot:actions>
      </x-ui.listing-toolbar>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl  bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1100px] text-left">
            <thead>
              <tr class="">
                <th class="w-[54px] p-2 text-[13px]  leading-[1.5] whitespace-nowrap">SI. No</th>
                <th class="w-[320px] p-2 text-[13px]  leading-[1.5] ">Campaign Name</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">Contacts</th>
                <th class="p-2 text-[13px] leading-[1.5] ">Templates</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">Complete</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">Statistics</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">Status</th>
                <th class="p-2 text-[13px] leading-[1.5] ">Actions</th>
                <th class="p-2 text-[13px]  leading-[1.5] ">En/Disable</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($campaigns as $index => $campaign)
                @php $serial = ($paginator->currentPage() - 1) * $paginator->perPage() + $index + 1; @endphp
                <tr class="border-t border-divider bg-elevated">
                  <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $serial }}</td>
                  <td class="w-[320px] p-2">
                    <div class="flex items-start gap-3">
                      <div class="mt-0.5 size-4 shrink-0 border-[1.5px] border-solid border-border-light bg-elevated"></div>
                      <div class="min-w-0">
                        <a href="{{ route('automation.drip.design', $campaign) }}" class="block text-[13px] font-semibold leading-[1.5] text-text-subtle hover:underline">
                          {{ $campaign->name }}
                        </a>
                        <p class="text-[13px] font-normal leading-[1.5] text-text-body">{{ $campaign->triggerLabel() }}</p>
                      </div>
                    </div>
                  </td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $campaign->states_count }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $campaign->nodeCount() }}</td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $campaign->completionRate() }}</td>
                  <td class="p-2">
                    <a href="{{ route('automation.drip.statistics', $campaign) }}" aria-label="View statistics" class="inline-flex">
                      <img src="{{ asset('images/automation/chart-bulk.svg') }}" alt="" class="size-6" width="24" height="24">
                    </a>
                  </td>
                  <td class="p-2">
                    @if ($campaign->isActive())
                      <span data-drip-status class="inline-flex items-center justify-center rounded bg-[rgba(0,128,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-[green]">
                        Running
                      </span>
                    @else
                      <span data-drip-status class="inline-flex items-center justify-center rounded bg-[rgba(0,0,0,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-text-muted">
                        Paused
                      </span>
                    @endif
                  </td>
                  <td class="p-2">
                    <div class="flex items-center gap-4">
                      <a href="{{ route('automation.drip.design', $campaign) }}" class="flex size-5 items-center justify-center" aria-label="Edit" title="Edit">
                        <img src="{{ asset('images/automation/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                      </a>
                      <x-automation.listing-delete-button
                        :action="route('automation.drip.destroy', $campaign)"
                        confirm="Delete this drip campaign? This action cannot be undone."
                        title="Delete campaign"
                        :ajax="true"
                      />
                    </div>
                  </td>
                  <td class="p-2">
                    <form method="POST" action="{{ route('automation.drip.toggle', $campaign) }}" data-drip-toggle class="flex items-center justify-center">
                      @csrf
                      @method('PATCH')
                      <x-ui.toggle-switch :active="$campaign->isActive()" :submit="true" aria-label="En/Disable" />
                    </form>
                  </td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="9" class="p-8 text-center text-sm text-text-body">
                    No drip campaigns yet. Click "Add New" to create your first campaign.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($paginator->hasPages())
          <x-ui.table-pagination :paginator="$paginator" />
        @endif
      </div>
    </section>
  </div>

  @push('scripts')
  <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
  @endpush
</x-layouts.app>
