<x-layouts.app title="Segments - WapApp" active="audience.segments">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <x-audience.list-header title="Segments" :subscribers="(string) $segments->total()" />
      <x-audience.sub-nav active="audience.segments" />
    </div>

    <section class="flex flex-col gap-4 p-4 pt-0">
      <x-ui.listing-toolbar
        :action="route('audience.segments')"
        :search-value="request('search')"
        :current-sort="$currentSort ?? request('sort', 'created_at')"
        :current-direction="$currentDirection ?? request('direction', 'desc')"
        :sort-options="[
          ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
          ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
          ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
          ['value' => 'contact_count', 'label' => 'Most subscribers', 'direction' => 'desc'],
        ]"
      >
        <x-slot:hidden>
          @if($mailListId)<input type="hidden" name="list" value="{{ $mailListId }}">@endif
        </x-slot:hidden>
        <x-slot:actions>
          <button type="button" data-open-modal="create-segment" class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
            <img src="{{ asset('images/icons/sidebar/dbfd6f4cd73e6e1ecbcca79a8be160d3f18f5172.svg') }}" alt="" class="size-5" width="20" height="20">
            Create Segments
          </button>
        </x-slot:actions>
      </x-ui.listing-toolbar>

      <x-ui.data-table :headers="['SI. No', 'Segment Name', 'Subscribers', 'Actions']" :paginator="$segments">
        @forelse ($segments as $i => $segment)
          <tr class="bg-elevated">
            <td class="fd-table-cell w-[54px] p-2">{{ $segments->firstItem() + $i }}</td>
            <td class="w-[420px] p-2">
              <p class="fd-table-name">{{ $segment->name }}</p>
              <p class="fd-table-cell">Created at: {{ $segment->created_at?->format('Y-m-d h:i A') ?? '-' }}</p>
            </td>
            <td class="w-[220px] p-2">
              <span class="inline-flex items-center rounded-lg border border-green-500 bg-elevated px-3 py-1 text-sm font-bold text-green-500 shadow-[0px_0px_2px_rgba(0,0,0,0.08)]">
                {{ number_format($segment->contact_count) }}
              </span>
            </td>
            <td class="p-2">
              <div class="flex justify-center gap-6">
                <form method="POST" action="{{ route('audience.segments.destroy', $segment) }}" class="inline" data-confirm="Delete this segment?" data-confirm-title="Delete segment" data-confirm-label="Delete">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="flex size-5 items-center justify-center" aria-label="Delete" title="Delete">
                    <img src="{{ asset('images/templates/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr class="bg-elevated">
            <td colspan="4" class="p-8 text-center text-sm text-text-body/70">No segments found.</td>
          </tr>
        @endforelse
      </x-ui.data-table>
    </section>
  </div>

  <x-audience.create-segment-modal :mail-list-id="$mailListId" :list-fields="$listFields ?? []" />
</x-layouts.app>
