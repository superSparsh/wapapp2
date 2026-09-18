<x-layouts.app title="Inbox - WapApp" active="inbox.index" mainOverflow="overflow-hidden">
  <div
    class="flex h-full min-h-0 flex-col overflow-hidden"
    data-inbox-root
    data-poll-interval="{{ config('inbox.poll_interval_ms', 30000) }}"
    data-realtime-fallback-poll="{{ config('inbox.realtime_fallback_poll_ms', 15000) }}"
    data-realtime-enabled="{{ config('inbox.realtime_enabled') && in_array(config('broadcasting.default'), ['reverb', 'pusher', 'ably'], true) ? '1' : '0' }}"
    data-tenant-id="{{ tenant('id') }}"
    data-add-contact-url="{{ route('inbox.api.contacts.store') }}"
    data-threads-url="{{ route('inbox.api.threads') }}"
    data-inbox-base-url="{{ url('/inbox') }}"
    data-export-all-url="{{ route('inbox.api.export-all') }}"
    data-wallet-blocked="{{ ! empty($walletBlocked) ? '1' : '0' }}"
    data-threads-cursor="{{ $threadsCursor ?? '' }}"
    data-threads-has-more="{{ ! empty($threadsHasMore) ? '1' : '0' }}"
    data-unread-total="{{ (int) ($unreadTotal ?? 0) }}"
    data-templates-url="{{ route('inbox.api.templates', array_filter(['line' => $activeLine?->uuid])) }}"
  >
    <div class="flex shrink-0 flex-col gap-3 p-4 pb-2">
      <x-ui.page-header title="Inbox" size="sm">
        <x-slot:actions>
          <button
            type="button"
            data-open-modal="add-contact"
            class="fd-btn inline-flex items-center gap-2 whitespace-nowrap rounded-lg bg-green-500 px-3 py-2 text-sm font-medium text-primary-2 transition hover:opacity-90"
          >
            <x-icons.nav-icon name="add" class="size-5" />
            Add New Contact
          </button>
        </x-slot:actions>
      </x-ui.page-header>

      @include('inbox.partials.toolbar', [
        'filters' => $filters ?? [],
        'filterOptions' => $filterOptions ?? [],
        'selectedConversation' => $selectedConversation ?? null,
        'availableLines' => $availableLines ?? [],
        'activeLine' => $activeLine ?? null,
        'aiForAll' => $aiForAll ?? false,
      ])
    </div>

    <div class="relative min-h-0 flex-1">
      <div class="flex h-full min-h-0 flex-col gap-4 overflow-hidden bg-surface px-4 pb-4 lg:flex-row" data-inbox-workspace>
        @include('inbox.partials.contact-list', [
          'threads' => $threads ?? [],
          'selectedConversation' => $selectedConversation ?? null,
          'filters' => $filters ?? [],
          'availableLines' => $availableLines ?? [],
          'activeLine' => $activeLine ?? null,
          'threadsCursor' => $threadsCursor ?? null,
          'threadsHasMore' => $threadsHasMore ?? false,
        ])

        @if (! empty($selectedConversation) && ! empty($selectedContact))
          @include('inbox.partials.chat-panel', [
            'contact' => $selectedContact,
            'messages' => $messages ?? [],
            'conversation' => $selectedConversation,
            'assignableAgents' => $assignableAgents ?? collect(),
            'menuOpen' => false,
            'walletBlocked' => $walletBlocked ?? false,
            'messagesHasMore' => $messagesHasMore ?? false,
            'messagesOldestId' => $messagesOldestId ?? null,
            'activeLine' => $activeLine ?? null,
          ])
        @else
          @include('inbox.partials.chat-empty')
        @endif
      </div>
    </div>
  </div>

  @include('inbox.partials.modals', ['activeModal' => $activeModal ?? null])
</x-layouts.app>
