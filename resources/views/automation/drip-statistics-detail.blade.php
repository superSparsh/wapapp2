<x-layouts.app title="Drip Statistics Detail - WapApp" active="automation.drip.index">
  <section class="flex flex-col bg-surface lg:flex-row lg:items-stretch">
    <x-automation.drip-flow-canvas :showEditFlow="true" :campaign="$campaign" />

    <div class="min-w-0 flex-1">
      <x-automation.drip-campaign-header :campaign="$campaign" activeTab="statistics">
        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
      <div class="flex w-full flex-col gap-2">
        <h1 class="text-2xl font-bold leading-[1.5] text-text-primary">
          {{ $campaign->name }}
        </h1>
        <p class="max-w-[854px] text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
          Detailed message log for this drip campaign.
        </p>
      </div>

      <div class="flex items-center justify-end gap-2">
        <h2 class="min-w-0 flex-1 text-xl font-bold leading-[1.5] text-text-primary">Total Sent Messages</h2>
        <a
          href="{{ route('automation.drip.statistics.export', $campaign) }}"
          class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-solid border-green-500 bg-green-50 px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-green-50"
        >
          <img src="{{ asset('images/automation/export-csv.svg') }}" alt="" class="size-4" width="16" height="16">
          Export to CSV
        </a>
      </div>
    </div>

    <section class="bg-surface p-4 pt-0">
      <div class="overflow-hidden rounded-xl bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[900px] text-left">
            <thead>
              <tr class="bg-elevated">
                <th class="w-[54px] p-2 text-[13px] font-medium leading-[1.5] whitespace-nowrap text-text-body">SI. No</th>
                <th class="w-[160px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">WhatsApp Number</th>
                <th class="w-[160px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Node ID</th>
                <th class="w-[200px] p-2 text-[13px] font-medium leading-[1.5] text-text-body">Node Type</th>
                <th class="p-2 text-center text-[13px] font-medium leading-[1.5] text-text-body">Status</th>
                <th class="p-2 text-[13px] font-medium leading-[1.5] text-text-body">Sent At</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($messages as $index => $stat)
                @php $serial = ($messages->currentPage() - 1) * $messages->perPage() + $index + 1; @endphp
                <tr class="border-t border-divider bg-elevated">
                  <td class="w-[54px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $serial }}</td>
                  <td class="w-[160px] p-2 text-[13px] font-semibold leading-[1.5] text-text-subtle">{{ $stat->contact_phone ?? 'N/A' }}</td>
                  <td class="w-[160px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $stat->node_id ?? 'N/A' }}</td>
                  <td class="w-[200px] p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $stat->node_type ?? 'N/A' }}</td>
                  <td class="p-2 text-center">
                    <span class="inline-flex items-center justify-center rounded bg-[rgba(156,163,175,0.1)] px-2 py-1 text-[10px] font-medium leading-[1.2] whitespace-nowrap text-text-muted">
                      {{ ucfirst($stat->action->value) }}
                    </span>
                  </td>
                  <td class="p-2 text-[13px] font-normal leading-[1.5] text-text-body">{{ $stat->created_at->format('d M Y h:i:s A') }}</td>
                </tr>
              @empty
                <tr class="border-t border-divider bg-elevated">
                  <td colspan="6" class="p-8 text-center text-sm text-text-body">No statistics recorded yet.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($messages->hasPages())
          <x-ui.table-pagination :paginator="$messages" />
        @endif
      </div>
    </section>
      </x-automation.drip-campaign-header>
    </div>
  </section>
  @push('scripts')
  <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
  @endpush
</x-layouts.app>
