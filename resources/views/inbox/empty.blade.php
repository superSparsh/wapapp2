<x-layouts.app title="Inbox - WapApp" active="inbox" mainOverflow="overflow-hidden">
  <div
    class="flex h-full min-h-0 flex-col overflow-hidden"
    data-inbox-root
    data-add-contact-url="{{ route('inbox.api.contacts.store') }}"
    data-threads-url="{{ route('inbox.api.threads') }}"
    data-inbox-base-url="{{ url('/inbox') }}"
  >
    <div class="flex shrink-0 flex-col gap-4 p-4 pb-3">
      <x-ui.page-header title="Inbox" />
      @include('inbox.partials.toolbar')
    </div>

    <div class="relative min-h-0 flex-1">
      <div class="flex h-full min-h-0 flex-col gap-4 overflow-hidden bg-surface px-4 pb-4 lg:flex-row" data-inbox-workspace>
      <div class="flex min-h-0 w-full shrink-0 flex-col gap-4 lg:w-[405px]" data-inbox-thread-panel>
        <form class="flex shrink-0 items-center gap-3 rounded-lg bg-elevated p-3" data-inbox-search-form>
          <x-icons.nav-icon name="search-inbox" class="size-5 shrink-0 text-text-body/60" />
          <input
            type="search"
            name="q"
            placeholder="Search by name or number"
            class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none"
            data-inbox-search-input
            autocomplete="off"
          >
        </form>

        <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[12px] bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
          <div class="flex min-h-0 flex-1 flex-col items-center justify-center px-8 py-16 text-center" data-inbox-thread-list>
            <div class="flex size-16 items-center justify-center rounded-full bg-green-50">
              <x-icons.nav-icon name="device-message" class="size-8 text-green-500" />
            </div>
            <h3 class="mt-4 text-base font-semibold text-text-subtle" style="font-family: var(--font-display)">No conversations yet</h3>
            <p class="mt-2 max-w-xs text-sm leading-[1.4] text-text-body/60" style="font-family: var(--font-display)">When customers message your WhatsApp number, conversations will appear here.</p>
            <button
              type="button"
              data-open-modal="add-contact"
              class="fd-btn mt-6 inline-flex items-center gap-2 rounded-lg bg-green-500 px-4 py-2 text-sm font-medium text-primary-2 hover:opacity-90"
            >
              <x-icons.nav-icon name="add" class="size-4" />
              Add New Contact
            </button>
          </div>
        </div>
      </div>

      <div class="relative flex h-full min-h-0 min-w-0 flex-1 items-center justify-center overflow-hidden rounded-xl">
        <img
          src="{{ asset('images/inbox/chat-wallpaper.png') }}"
          alt=""
          class="pointer-events-none absolute inset-0 size-full rounded-xl object-cover opacity-40"
        >
        <div class="relative flex flex-col items-center text-center">
          <div class="flex size-20 items-center justify-center rounded-full bg-elevated/80">
            <x-icons.nav-icon name="device-message" class="size-10 text-text-body/40" />
          </div>
          <p class="mt-4 text-sm text-text-body/60" style="font-family: var(--font-display)">Select a conversation to start messaging</p>
        </div>
      </div>
      </div>
    </div>
  </div>

  @include('inbox.partials.modals', ['activeModal' => null])
</x-layouts.app>
