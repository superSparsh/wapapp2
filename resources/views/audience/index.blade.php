<x-layouts.app title="Audience - WapApp" active="audience.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4 p-4">
      <div class="flex flex-col gap-1">
        <h1 class="fd-page-title text-2xl">My lists</h1>
        <p class="fd-page-note">Welcome back! Here's your loan business overview.</p>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('audience.index') }}" class="flex w-full max-w-[550px] items-center gap-3 rounded-lg bg-elevated p-3">
          <img src="{{ asset('images/icons/sidebar/059a8053c8ef2ad2aae8a0b9c28cef8b48c5e37b.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          <input type="search" name="search" value="{{ request('search') }}" placeholder="Search" class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none">
        </form>

        <div class="flex flex-wrap items-center gap-2">
          <a href="{{ route('audience.index') }}" class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-surface">
            <img src="{{ asset('images/automation/refresh.svg') }}" alt="" class="size-4" width="16" height="16">
            Refresh
          </a>
          <button type="button" data-open-modal="create-list" class="fd-btn inline-flex items-center justify-center gap-2 rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
            <img src="{{ asset('images/icons/sidebar/dbfd6f4cd73e6e1ecbcca79a8be160d3f18f5172.svg') }}" alt="" class="size-5" width="20" height="20">
            Create List
          </button>
        </div>
      </div>
    </div>

    <section class="p-4 pt-0">
      @php
        $lists = $lists ?? new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
      @endphp
      <x-ui.data-table :headers="['SI. No', 'My lists', 'Subscriber\'s', 'Actions']" :paginator="$lists">
        @foreach ($lists as $i => $list)
          <tr class="bg-elevated">
            <td class="fd-table-cell w-[54px] p-2">{{ $lists->firstItem() + $i }}</td>
            <td class="w-[320px] p-2">
              <a href="{{ route('audience.overview', ['list' => $list->id]) }}" class="fd-table-name hover:underline">{{ $list->name }}</a>
              <p class="fd-table-cell">Created at: {{ $list->created_at?->format('Y-m-d h:i A') }}</p>
            </td>
            <td class="p-2">
              <div class="flex flex-wrap items-center justify-center gap-2.5">
                <span class="inline-flex items-center rounded px-2 py-1 text-[13px] font-medium leading-[1.2] text-stat-emerald bg-[rgba(16,185,129,0.1)]">{{ $list->subscribed_count ?? 0 }} Subscribed</span>
                <span class="inline-flex items-center rounded px-2 py-1 text-[13px] font-medium leading-[1.2] text-text-muted bg-[rgba(156,163,175,0.1)]">{{ $list->unsubscribed_count ?? 0 }} UnSubscribed</span>
                <span class="inline-flex items-center rounded px-2 py-1 text-[13px] font-medium leading-[1.2] text-danger bg-[rgba(239,68,68,0.1)]">{{ $list->blacklisted_count ?? 0 }} Blacklisted</span>
                <span class="inline-flex items-center rounded px-2 py-1 text-[13px] font-medium leading-[1.2] text-stat-blue bg-[rgba(59,130,246,0.1)]">{{ $list->contacts_count ?? 0 }} Total</span>
              </div>
            </td>
            <td class="w-[240px] p-2">
              <div class="flex items-center justify-center gap-6">
                <a href="{{ route('audience.overview', ['list' => $list->id]) }}" class="flex size-5 items-center justify-center" aria-label="Chart">
                  <img src="{{ asset('images/audience/chart.svg') }}" alt="" class="size-5" width="20" height="20">
                </a>
                <button type="button" data-open-modal="import-subscribers" class="flex size-5 items-center justify-center" aria-label="Import subscribers">
                  <img src="{{ asset('images/icons/user-add.svg') }}" alt="" class="size-5" width="20" height="20">
                </button>
                <a href="{{ route('audience.settings', ['list' => $list->id]) }}" class="flex size-5 items-center justify-center" aria-label="Edit">
                  <img src="{{ asset('images/templates/edit.svg') }}" alt="" class="size-5" width="20" height="20">
                </a>
                <form method="POST" action="{{ route('audience.lists.destroy', $list) }}" class="inline" onsubmit="return confirm('Delete this list?')">
                  @csrf @method('DELETE')
                  <button type="submit" class="flex size-5 items-center justify-center" aria-label="Trash">
                    <img src="{{ asset('images/templates/trash.svg') }}" alt="" class="size-5" width="20" height="20">
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @endforeach
      </x-ui.data-table>
    </section>
  </div>

  <x-audience.import-subscribers-modal />
  <x-audience.create-list-modal />
</x-layouts.app>
