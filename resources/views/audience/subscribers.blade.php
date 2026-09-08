<x-layouts.app title="Subscribers - WapApp" active="audience.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4">
      <x-audience.list-header :title="$mailList?->name ?? ($mailListId ? '' : 'All Subscribers')" :subscribers="(string) $contacts->total()" />
      <x-audience.sub-nav active="audience.subscribers" />
    </div>

    <section class="flex flex-col gap-4 p-4 pt-5">
      <div class="flex flex-wrap items-center justify-end gap-3">
        <div class="relative" data-filter-dropdown>
          <button type="button" class="flex w-full max-w-[221px] items-center justify-between rounded-lg bg-elevated p-3 sm:w-[221px]" data-filter-toggle>
            <span class="fd-filter-label">
              @if(request('status') === 'subscribed') Subscribed
              @elseif(request('status') === 'unsubscribed') Unsubscribed
              @elseif(request('status') === 'blacklisted') Blacklisted
              @else Filter Subscribers
              @endif
            </span>
            <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
          </button>
          <div class="absolute right-0 z-30 mt-1 hidden w-52 rounded-lg border border-border-light bg-elevated py-1 shadow-lg" data-filter-menu>
            <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => null])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ !request('status') ? 'bg-green-50 text-green-600 font-semibold' : '' }}">All subscribers</a>
            <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => 'subscribed'])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ request('status') === 'subscribed' ? 'bg-green-50 text-green-600 font-semibold' : '' }}">Subscribed</a>
            <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => 'unsubscribed'])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ request('status') === 'unsubscribed' ? 'bg-green-50 text-green-600 font-semibold' : '' }}">Unsubscribed</a>
            <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => 'blacklisted'])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ request('status') === 'blacklisted' ? 'bg-green-50 text-green-600 font-semibold' : '' }}">Blacklisted</a>
          </div>
        </div>
        <button type="button" data-open-modal="new-subscriber" class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
          <img src="{{ asset('images/icons/sidebar/dbfd6f4cd73e6e1ecbcca79a8be160d3f18f5172.svg') }}" alt="" class="size-5" width="20" height="20">
          Add New Subscribers
        </button>
        <button type="button" data-open-modal="import-subscribers" class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-green-100 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50/80">
          <img src="{{ asset('images/automation/refresh.svg') }}" alt="" class="size-4" width="16" height="16">
          Import
        </button>
        <form method="POST" action="{{ route('audience.subscribers.export') }}" class="inline">
          @csrf
          @if($mailListId)<input type="hidden" name="mail_list_id" value="{{ $mailListId }}">@endif
          <button type="submit" class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-green-100 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50/80">
            <img src="{{ asset('images/automation/refresh.svg') }}" alt="" class="size-4" width="16" height="16">
            Export
          </button>
        </form>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
          <div class="size-4 shrink-0 border-[1.5px] border-border-light bg-elevated"></div>
          <span class="fd-filter-label font-semibold text-primary-2">Sort by</span>
        </div>
        <div class="relative" data-sort-dropdown>
          <button type="button" class="flex w-[221px] items-center justify-between rounded-lg bg-elevated p-3" data-sort-toggle>
            <span class="fd-filter-label">
              @php
                $sortLabels = ['created_at' => 'Created at', 'updated_at' => 'Updated at', 'name' => 'Name', 'phone' => 'Phone'];
                $currentSort = request('sort_by', 'created_at');
                $currentDir = request('sort_dir', 'desc');
              @endphp
              {{ $sortLabels[$currentSort] ?? 'Created at' }} {{ $currentDir === 'asc' ? '↑' : '↓' }}
            </span>
            <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
          </button>
          <div class="absolute right-0 z-30 mt-1 hidden w-52 rounded-lg border border-border-light bg-elevated py-1 shadow-lg" data-sort-menu>
            @foreach(['created_at' => 'Created at', 'updated_at' => 'Updated at', 'name' => 'Name', 'phone' => 'Phone'] as $sortKey => $sortLabel)
              <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['sort_by' => $sortKey, 'sort_dir' => ($currentSort === $sortKey && $currentDir === 'desc') ? 'asc' : 'desc'])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ $currentSort === $sortKey ? 'bg-green-50 text-green-600 font-semibold' : '' }}">{{ $sortLabel }} {{ $currentSort === $sortKey ? ($currentDir === 'asc' ? '↑' : '↓') : '' }}</a>
            @endforeach
          </div>
        </div>
        <form method="GET" action="{{ route('audience.subscribers') }}" class="flex w-full max-w-[550px] flex-1 items-center gap-3 rounded-lg bg-elevated p-3">
          @if($mailListId)<input type="hidden" name="list" value="{{ $mailListId }}">@endif
          @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
          <img src="{{ asset('images/icons/sidebar/059a8053c8ef2ad2aae8a0b9c28cef8b48c5e37b.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
          <input type="search" name="search" value="{{ request('search') }}" placeholder="Search" class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none">
        </form>
      </div>

      <x-ui.data-table :headers="['SI. No', 'Whatsapp Number', 'Status', 'Name', 'Opt-in Message', 'Created At', 'Updated At', 'Un/Subcribe', 'Actions']" :paginator="$contacts">
        @forelse ($contacts as $i => $contact)
          <tr class="bg-elevated">
            <td class="fd-table-cell w-[54px] p-2">{{ $contacts->firstItem() + $i }}</td>
            <td class="w-[200px] p-2">
              <div class="flex items-center gap-3">
                <div class="size-4 shrink-0 border-[1.5px] border-border-light bg-elevated"></div>
                <img src="{{ asset('images/audience/avatar-placeholder.svg') }}" alt="" class="size-8 shrink-0 rounded-full" width="32" height="32">
                <span class="fd-table-name whitespace-nowrap">{{ $contact->phone }}</span>
              </div>
            </td>
            <td class="w-[88px] p-2">
              <span @class([
                'inline-flex items-center rounded px-2 py-1 text-[10px] font-medium leading-[1.2]',
                'bg-[rgba(0,128,0,0.1)] text-green-600' => $contact->status?->value === 'subscribed',
                'bg-black/10 text-[#767676]' => $contact->status?->value === 'unsubscribed',
                'bg-red-50 text-red-600' => $contact->status?->value === 'blacklisted',
              ])>{{ $contact->status?->label() ?? 'Unknown' }}</span>
            </td>
            <td class="fd-table-cell p-2">{{ $contact->name ?? '-' }}</td>
            <td class="w-[140px] p-2">
              @if($contact->send_opt_in_message === 'yes')
                @php $deliveryStatus = $contact->opt_in_message_delivery_status; @endphp
                @if($deliveryStatus === 'delivered')
                  <span class="inline-flex items-center rounded bg-green-50 px-2 py-1 text-[10px] font-medium text-green-600">Delivered</span>
                @elseif($deliveryStatus === 'failed')
                  <span class="inline-flex items-center rounded bg-red-50 px-2 py-1 text-[10px] font-medium text-red-600">Failed</span>
                @elseif($deliveryStatus === 'pending')
                  <span class="inline-flex items-center rounded bg-yellow-50 px-2 py-1 text-[10px] font-medium text-yellow-600">Pending</span>
                @elseif($contact->opt_in_message_sent)
                  <span class="inline-flex items-center rounded bg-gray-100 px-2 py-1 text-[10px] font-medium text-gray-600">Sent</span>
                @else
                  <span class="text-[10px] text-text-body/60">Not sent yet</span>
                @endif
              @else
                <span class="text-[10px] text-text-body/40">&mdash;</span>
              @endif
            </td>
            <td class="fd-table-cell p-2">{{ $contact->created_at?->format('d M Y H:i') ?? '-' }}</td>
            <td class="fd-table-cell p-2">{{ $contact->updated_at?->format('d M Y H:i') ?? '-' }}</td>
            <td class="w-[80px] p-2 text-center">
              <form method="POST" action="{{ route('audience.subscribers.' . ($contact->status?->value === 'subscribed' ? 'unsubscribe' : 'subscribe')) }}">
                @csrf
                <input type="hidden" name="ids[]" value="{{ $contact->id }}">
                <button type="submit">
                  <x-ui.toggle-switch :active="$contact->status?->value === 'subscribed'" />
                </button>
              </form>
            </td>
            <td class="w-[80px] p-2">
              <x-ui.table-actions
                :actions="['edit', 'trash']"
                :links="[
                  'edit' => route('audience.subscribers.detail', ['id' => $contact->id]),
                  'trash' => route('audience.subscribers.destroy', $contact),
                ]"
              />
            </td>
          </tr>
        @empty
          <tr class="bg-elevated">
            <td colspan="9" class="p-8 text-center text-sm text-text-body/70">
              No subscribers yet. Use <span class="font-semibold text-text-primary">Add New Subscribers</span> to add one.
            </td>
          </tr>
        @endforelse
      </x-ui.data-table>
    </section>
  </div>

  <div id="modal-new-subscriber" data-modal="new-subscriber" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="modal-title-new-subscriber">
    <div class="flex w-full max-w-[597px] flex-col gap-4 rounded-[20px] bg-elevated p-5 shadow-[0px_4px_6px_rgba(0,0,0,0.1)]">
      <div class="flex items-start justify-end gap-4">
        <div class="min-w-0 flex-1">
          <h2 id="modal-title-new-subscriber" class="text-2xl font-bold leading-[1.5] text-text-primary">New subscriber</h2>
          <p class="mt-1 text-sm font-normal leading-[1.4] text-text-subtle opacity-50">Adding new subscriber</p>
        </div>
        <button type="button" data-modal-close aria-label="Close" class="flex size-6 shrink-0 items-center justify-center rounded hover:bg-muted-surface">
          <img src="{{ asset('images/inbox/modals/close-square.svg') }}" alt="" class="size-6" width="24" height="24">
        </button>
      </div>

      <form method="POST" action="{{ route('audience.subscribers.store') }}" class="space-y-4">
        @csrf
        @if($mailListId)
          <input type="hidden" name="mail_list_id" value="{{ $mailListId }}">
        @else
          <div>
            <label for="subscriber_mail_list" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">List</label>
            <select id="subscriber_mail_list" name="mail_list_id" class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
              <option value="">All / no list</option>
              @foreach ($mailLists ?? [] as $listOption)
                <option value="{{ $listOption->id }}">{{ $listOption->name }}</option>
              @endforeach
            </select>
          </div>
        @endif
        <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
          <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-4 sm:flex-row">
              <div class="w-full sm:w-[160px]">
                <label for="subscriber_country_code" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
                  Country Code
                </label>
                <div class="relative">
                  <select id="subscriber_country_code" name="country_code" class="w-full appearance-none rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] pr-10 text-sm font-medium leading-[1.4] text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
                    @foreach (($phoneCodes ?? config('account.phone_codes', [])) as $iso => $code)
                      <option value="{{ $code }}" @selected($iso === 'IN')>{{ ($countries[$iso] ?? $iso) }} (+{{ $code }})</option>
                    @endforeach
                  </select>
                  <x-icons.nav-icon name="arrow-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2" />
                </div>
              </div>

              <div class="min-w-0 flex-1">
                <label for="subscriber_phone" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
                  WhatsApp Phone Number <span class="text-red-500">*</span>
                </label>
                <input id="subscriber_phone" name="phone" type="text" required placeholder="Enter Phone Number" class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
              </div>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row">
              <div class="min-w-0 flex-1">
                <label for="subscriber_name" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">Name</label>
                <input id="subscriber_name" name="name" type="text" placeholder="Enter Name" class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
              </div>
              <div class="min-w-0 flex-1">
                <label for="subscriber_email" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">Email</label>
                <input id="subscriber_email" name="email" type="email" placeholder="Enter Email" class="w-full rounded-[12px] border border-border bg-elevated px-[14px] py-[14px] text-sm font-medium leading-[1.4] text-text-muted placeholder:text-text-muted focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500">
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <button type="button" data-modal-close class="fd-btn rounded border border-green-500 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50">Cancel</button>
          <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">Create</button>
        </div>
      </form>
    </div>
  </div>

  <x-audience.import-subscribers-modal />
  <x-audience.export-subscribers-modal />

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // Filter dropdown toggle
    const filterToggle = document.querySelector('[data-filter-toggle]');
    const filterMenu = document.querySelector('[data-filter-menu]');
    if (filterToggle && filterMenu) {
      filterToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        filterMenu.classList.toggle('hidden');
      });
    }

    // Sort dropdown toggle
    const sortToggle = document.querySelector('[data-sort-toggle]');
    const sortMenu = document.querySelector('[data-sort-menu]');
    if (sortToggle && sortMenu) {
      sortToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sortMenu.classList.toggle('hidden');
      });
    }

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
      filterMenu?.classList.add('hidden');
      sortMenu?.classList.add('hidden');
    });
  });
  </script>
</x-layouts.app>
