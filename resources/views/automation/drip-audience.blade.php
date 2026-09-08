<x-layouts.app title="Drip Audience - WapApp" active="automation.drip.index">
  <section class="flex flex-col bg-surface lg:flex-row lg:items-stretch">
    <x-automation.drip-flow-canvas :showEditFlow="true" :campaign="$campaign" />

    <div class="min-w-0 flex-1">
      <x-automation.drip-campaign-header :campaign="$campaign" activeTab="audience">
        <div class="relative flex h-[30px] w-full items-center justify-between">
          <x-automation.audience-view-tabs :campaign="$campaign" active="audience" />
        </div>

        <div class="flex flex-col gap-4 bg-surface px-4 pb-4">
          <x-automation.audience-stats-grid
            :contactsInAction="$statsGrid['in_action']"
            :contactsDone="$statsGrid['done']"
            :skippedPending="$statsGrid['pending']"
            :errors="$statsGrid['errors']"
          />

          <p class="w-full text-sm font-normal leading-[1.4] text-text-subtle opacity-50">
            There are&nbsp;{{ $totalContacts }}&nbsp;contacts in total targeted for this automation / campaign
          </p>

          <form method="GET" action="{{ route('automation.drip.audience', $campaign) }}" class="flex w-full flex-col gap-3">
            <div class="flex w-full items-center gap-3 overflow-hidden rounded-lg bg-elevated p-3">
              <img src="{{ asset('images/automation/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
              <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search"
                class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body/60 placeholder:text-text-body/60 placeholder:opacity-60 focus:outline-none"
                data-drip-search
              >
            </div>

            <div class="flex w-full items-start gap-2">
              <a
                href="{{ route('automation.drip.audience', $campaign) }}"
                class="fd-btn flex min-w-0 flex-1 items-center justify-center gap-3 rounded border border-solid border-border-light bg-elevated px-4 py-3 text-[10px] font-semibold leading-[1.5] whitespace-nowrap text-green-500 transition-colors hover:bg-surface"
              >
                <img src="{{ asset('images/automation/refresh-2.svg') }}" alt="" class="size-4" width="16" height="16">
                Refresh
              </a>
            </div>
          </form>

          <div class="flex w-full flex-col items-start">
            <div class="mb-2 flex w-full flex-col gap-2">
              <p class="text-xs font-medium leading-[1.5] text-text-body">All Contacts</p>
              <img
                src="{{ asset('images/automation/contact-divider.svg') }}"
                alt=""
                class="w-full"
                width="100%"
                height="1"
              >
            </div>

            <div class="flex w-full flex-col items-start">
              @forelse ($contacts as $contact)
                <div
                  data-contact-phone="{{ $contact->contact_phone }}"
                  @class([
                  'flex w-full items-center justify-between px-2 py-2.5',
                  'bg-muted-surface' => $loop->even,
                ])>
                  <div class="flex w-[195px] shrink-0 items-center gap-2">
                    <div class="size-8 shrink-0 rounded-full bg-border"></div>
                    <div class="min-w-0 flex-1">
                      <p class="text-xs font-semibold leading-[1.5] text-text-subtle">{{ $contact->contact_phone }}</p>
                      <p class="text-[10px] font-normal leading-[1.5] text-blue-200">{{ $contact->stat_count }} activities</p>
                    </div>
                  </div>

                  <div class="w-[102px] shrink-0 text-right text-[10px] leading-[1.5]">
                    @if ($contact->state_status === 'updated' || $contact->state_status === 'completed')
                      <p class="font-semibold text-text-subtle">{{ \Carbon\Carbon::parse($contact->last_activity)->diffForHumans() }}</p>
                      <p class="font-normal whitespace-nowrap text-text-body">
                        Last updated <span class="text-green-500 underline">Refresh</span>
                      </p>
                    @else
                      <p class="font-semibold text-[#ff8a05]">Waiting</p>
                      <button
                        type="button"
                        data-open-modal="trigger-confirm"
                        class="font-normal text-[#ff8a05] underline"
                      >
                        Trigger Now
                      </button>
                    @endif
                  </div>
                </div>
              @empty
                <p class="w-full py-8 text-center text-sm text-text-body">No contacts found for this campaign.</p>
              @endforelse
            </div>
          </div>

          @if ($contacts->hasPages())
            <x-ui.table-pagination :paginator="$contacts" />
          @endif

          <form
            method="POST"
            action="{{ route('automation.drip.audience.trigger', $campaign) }}"
            data-trigger-form
            class="hidden"
          >
            @csrf
            <input type="hidden" name="phone" value="">
          </form>
        </div>
      </x-automation.drip-campaign-header>
    </div>
  </section>

  @push('scripts')
  <script src="{{ asset('js/drip/drip-marketing.js') }}" defer></script>
  @endpush
</x-layouts.app>
