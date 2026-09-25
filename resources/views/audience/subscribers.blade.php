<x-layouts.app title="Subscribers - WapApp" active="audience.index">
  <div class="flex flex-col bg-surface">
    <div class="flex flex-col gap-4">
      <x-audience.list-header :title="$mailList->name" :subscribers="(string) $contacts->total()" />
      <x-audience.sub-nav active="audience.subscribers" :list-id="$mailListId" />
    </div>

    <section class="flex flex-col gap-4 p-4 pt-5">
      @if (session('status'))
        <p class="rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">{{ session('status') }}</p>
      @endif
      <div class="flex flex-wrap items-center justify-between gap-3">
        <form id="bulk-subscribers-form" method="POST" class="flex flex-wrap items-center gap-2" data-bulk-form>
          @csrf
          @if($mailListId)<input type="hidden" name="list" value="{{ $mailListId }}">@endif
          @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
          <select name="bulk_action" data-bulk-action class="rounded-lg border border-border bg-elevated px-3 py-2 text-sm">
            <option value="">Bulk actions</option>
            <option value="subscribe">Subscribe</option>
            <option value="unsubscribe">Unsubscribe</option>
            <option value="delete">Delete</option>
          </select>
          <button type="submit" class="fd-btn rounded border border-green-500 px-3 py-2 text-sm font-semibold text-green-500 disabled:opacity-50" data-bulk-submit disabled>
            Apply
          </button>
        </form>

        <div class="flex flex-wrap items-center justify-end gap-3">
          <div class="relative" data-filter-dropdown>
            <button type="button" class="flex w-full max-w-[221px] items-center justify-between rounded-lg bg-elevated p-3 sm:w-[221px]" data-filter-toggle>
              <span class="fd-filter-label">
                @if(request('status') === 'subscribed') Subscribed
                @elseif(request('status') === 'unsubscribed') Unsubscribed
                @elseif(request('status') === 'blacklisted') Blacklisted
                @else Filter by status
                @endif
              </span>
              <x-icons.nav-icon name="arrow-down" class="size-4 shrink-0" />
            </button>
            <div class="absolute right-0 z-30 mt-1 hidden w-52 rounded-lg border border-border-light bg-elevated py-1 shadow-lg" data-filter-menu>
              <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => null])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ !request('status') ? 'bg-green-50 text-green-600 font-semibold' : '' }}">All statuses</a>
              <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => 'subscribed'])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ request('status') === 'subscribed' ? 'bg-green-50 text-green-600 font-semibold' : '' }}">Subscribed</a>
              <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => 'unsubscribed'])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ request('status') === 'unsubscribed' ? 'bg-green-50 text-green-600 font-semibold' : '' }}">Unsubscribed</a>
              <a href="{{ route('audience.subscribers', array_merge(request()->query(), ['status' => 'blacklisted'])) }}" class="block px-4 py-2 text-sm text-text-body hover:bg-muted-surface {{ request('status') === 'blacklisted' ? 'bg-green-50 text-green-600 font-semibold' : '' }}">Blacklisted</a>
            </div>
          </div>
          <button type="button" data-open-modal="new-subscriber" class="fd-btn inline-flex items-center justify-center rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">
            <img src="{{ asset('images/icons/sidebar/dbfd6f4cd73e6e1ecbcca79a8be160d3f18f5172.svg') }}" alt="" class="size-5" width="20" height="20">
            Add New Subscribers
          </button>
          <button type="button" data-open-modal="import-subscribers" @if($mailListId) data-mail-list-id="{{ $mailListId }}" data-mail-list-name="{{ $mailList?->name }}" @endif class="fd-btn inline-flex items-center justify-center rounded-lg border border-green-500 bg-green-100 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50/80">
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
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <x-ui.listing-toolbar
          class="flex-1"
          :action="route('audience.subscribers')"
          :search-value="$search ?? ''"
          :current-sort="$currentSort ?? 'created_at'"
          :current-direction="$currentDirection ?? 'desc'"
          :sort-options="[
            ['value' => 'created_at', 'label' => 'Newest first', 'direction' => 'desc'],
            ['value' => 'created_at', 'label' => 'Oldest first', 'direction' => 'asc'],
            ['value' => 'updated_at', 'label' => 'Recently updated', 'direction' => 'desc'],
            ['value' => 'name', 'label' => 'Name A–Z', 'direction' => 'asc'],
            ['value' => 'name', 'label' => 'Name Z–A', 'direction' => 'desc'],
            ['value' => 'phone', 'label' => 'Phone A–Z', 'direction' => 'asc'],
            ['value' => 'phone', 'label' => 'Phone Z–A', 'direction' => 'desc'],
          ]"
        >
          <x-slot:hidden>
            @if($mailListId)<input type="hidden" name="list" value="{{ $mailListId }}">@endif
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
          </x-slot:hidden>
          <x-slot:filters>
            <div class="flex shrink-0 items-center gap-2">
              <label class="sr-only" for="subscribers-date-from">From date</label>
              <input
                id="subscribers-date-from"
                type="date"
                name="date_from"
                value="{{ $dateFrom ?? '' }}"
                data-listing-filter
                class="rounded-lg border border-border bg-elevated px-2.5 py-2 text-xs font-medium text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                title="Filter by created from"
              >
              <span class="text-xs text-text-body/60">to</span>
              <label class="sr-only" for="subscribers-date-to">To date</label>
              <input
                id="subscribers-date-to"
                type="date"
                name="date_to"
                value="{{ $dateTo ?? '' }}"
                data-listing-filter
                class="rounded-lg border border-border bg-elevated px-2.5 py-2 text-xs font-medium text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                title="Filter by created to"
              >
            </div>
            <div class="min-w-[180px] shrink-0">
              <x-ui.select name="opt_in" variant="listing" class="min-w-[180px]" data-listing-filter aria-label="Opt-in message filter">
                <option value="">Opt-in message</option>
                <option value="send_yes" @selected(($optIn ?? '') === 'send_yes')>Send opt-in: Yes</option>
                <option value="send_no" @selected(($optIn ?? '') === 'send_no')>Send opt-in: No</option>
                <option value="not_sent" @selected(($optIn ?? '') === 'not_sent')>Not sent yet</option>
                <option value="pending" @selected(($optIn ?? '') === 'pending')>Delivery pending</option>
                <option value="delivered" @selected(($optIn ?? '') === 'delivered')>Delivered</option>
                <option value="failed" @selected(($optIn ?? '') === 'failed')>Failed</option>
                <option value="sent_awaiting" @selected(($optIn ?? '') === 'sent_awaiting')>Sent — awaiting status</option>
              </x-ui.select>
            </div>
          </x-slot:filters>
        </x-ui.listing-toolbar>
      </div>

      <x-ui.data-table :headers="['SI. No', 'Whatsapp Number', 'Status', 'Name', 'Opt-in Message', 'Created At', 'Updated At', 'Un/Subscribe', 'Actions']" :paginator="$contacts">
        @forelse ($contacts as $i => $contact)
          <tr class="bg-elevated">
            <td class="fd-table-cell w-[54px] p-2">{{ $contacts->firstItem() + $i }}</td>
            <td class="w-[200px] p-2">
              <div class="flex items-center gap-3">
                <input
                  type="checkbox"
                  form="bulk-subscribers-form"
                  name="ids[]"
                  value="{{ $contact->uuid }}"
                  class="size-4 shrink-0 rounded border-border-light"
                  data-bulk-checkbox
                >
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
                @php
                  $deliveryStatus = $contact->opt_in_message_delivery_status;
                  $isNonWa = $contact->tags->contains(fn ($t) => strtolower((string) $t->name) === 'non whatsapp number')
                    || str_contains((string) ($contact->opt_in_message_delivery_error ?? ''), '131026');
                @endphp
                <div class="flex flex-col gap-1">
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
                  @if($isNonWa)
                    <span class="inline-flex items-center rounded bg-yellow-50 px-2 py-1 text-[10px] font-medium text-yellow-700">non whatsapp number</span>
                  @endif
                  @if($deliveryStatus === 'failed' && filled($contact->opt_in_message_delivery_error))
                    <span class="text-[10px] leading-snug text-red-500" title="{{ $contact->opt_in_message_delivery_error }}">{{ \Illuminate\Support\Str::limit($contact->opt_in_message_delivery_error, 80) }}</span>
                  @endif
                </div>
              @else
                <span class="text-[10px] text-text-body/40">&mdash;</span>
              @endif
            </td>
            <td class="fd-table-cell p-2">{{ $contact->created_at?->format('d M Y H:i') ?? '-' }}</td>
            <td class="fd-table-cell p-2">{{ $contact->updated_at?->format('d M Y H:i') ?? '-' }}</td>
            <td class="w-[80px] p-2 text-center">
              <form
                method="POST"
                action="{{ route(
                  'audience.subscribers.' . ($contact->status?->value === 'subscribed' ? 'unsubscribe' : 'subscribe'),
                  array_filter(['list' => $mailListId, 'status' => request('status')])
                ) }}"
              >
                @csrf
                <input type="hidden" name="ids[]" value="{{ $contact->uuid }}">
                <x-ui.toggle-switch
                  :active="$contact->status?->value === 'subscribed'"
                  :submit="true"
                  aria-label="{{ $contact->status?->value === 'subscribed' ? 'Unsubscribe contact' : 'Subscribe contact' }}"
                />
              </form>
            </td>
            <td class="w-[80px] p-2">
              <x-ui.table-actions
                :actions="['edit', 'trash']"
                :links="[
                  'edit' => route('audience.subscribers.detail', array_filter(['id' => $contact->uuid, 'list' => $mailListId])),
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
        <input type="hidden" name="mail_list_id" value="{{ $mailListId }}">
        <div class="rounded-[12px] border border-border-light bg-muted-surface p-4">
          <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-4 sm:flex-row">
              <div class="w-full sm:w-[160px]">
                <label for="subscriber_country_code" class="mb-2 block text-sm font-semibold leading-[1.4] text-text-primary">
                  Country Code
                </label>
                <x-ui.country-code-select id="subscriber_country_code" name="country_code" />
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

            <label class="flex items-center gap-2 text-sm font-medium text-text-body">
              <input type="hidden" name="send_opt_in_message" value="no">
              <input type="checkbox" name="send_opt_in_message" value="yes" class="size-4 rounded border-border">
              Send WhatsApp opt-in message
            </label>
          </div>
        </div>

        <div class="flex items-center justify-between">
          <button type="button" data-modal-close class="fd-btn rounded border border-green-500 px-4 py-3 text-sm font-semibold text-green-500 transition-colors hover:bg-green-50">Cancel</button>
          <button type="submit" class="fd-btn rounded bg-green-500 px-4 py-3 text-sm font-semibold text-primary-2 transition-colors hover:opacity-90">Create</button>
        </div>
      </form>
    </div>
  </div>

  <x-audience.import-subscribers-modal :mail-list-id="$mailListId" :mail-list-name="$mailList?->name" :mail-lists="$mailLists ?? []" />
  <x-audience.export-subscribers-modal />

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const filterToggle = document.querySelector('[data-filter-toggle]');
    const filterMenu = document.querySelector('[data-filter-menu]');
    if (filterToggle && filterMenu) {
      filterToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        filterMenu.classList.toggle('hidden');
      });
    }

    const sortToggle = document.querySelector('[data-sort-toggle]');
    const sortMenu = document.querySelector('[data-sort-menu]');
    if (sortToggle && sortMenu) {
      sortToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        sortMenu.classList.toggle('hidden');
      });
    }

    document.addEventListener('click', () => {
      filterMenu?.classList.add('hidden');
      sortMenu?.classList.add('hidden');
    });

    const bulkForm = document.querySelector('[data-bulk-form]');
    const bulkSubmit = document.querySelector('[data-bulk-submit]');
    const bulkAction = document.querySelector('[data-bulk-action]');
    const syncBulk = () => {
      const checked = document.querySelectorAll('[data-bulk-checkbox]:checked').length;
      if (bulkSubmit) bulkSubmit.disabled = checked === 0 || !bulkAction?.value;
    };
    document.querySelectorAll('[data-bulk-checkbox]').forEach((el) => el.addEventListener('change', syncBulk));
    bulkAction?.addEventListener('change', syncBulk);

    bulkForm?.addEventListener('submit', (event) => {
      const action = bulkAction?.value;
      const routes = {
        subscribe: @json(route('audience.subscribers.subscribe')),
        unsubscribe: @json(route('audience.subscribers.unsubscribe')),
        delete: @json(route('audience.subscribers.bulk-delete')),
      };
      if (!action || !routes[action]) {
        event.preventDefault();
        return;
      }

      bulkForm.setAttribute('action', routes[action]);

      if (action !== 'delete' || bulkForm.dataset.confirmBypass === 'true') {
        return;
      }

      event.preventDefault();

      const ask = typeof window.showAppConfirm === 'function'
        ? window.showAppConfirm({
            title: 'Delete subscribers',
            message: 'Delete selected subscribers? This cannot be undone.',
            variant: 'danger',
            confirmLabel: 'Delete',
          })
        : Promise.resolve(window.confirm('Delete selected subscribers?'));

      ask.then((confirmed) => {
        if (!confirmed) {
          return;
        }

        bulkForm.dataset.confirmBypass = 'true';
        if (typeof bulkForm.requestSubmit === 'function') {
          bulkForm.requestSubmit();
        } else {
          bulkForm.submit();
        }
        delete bulkForm.dataset.confirmBypass;
      });
    });
  });
  </script>
</x-layouts.app>
