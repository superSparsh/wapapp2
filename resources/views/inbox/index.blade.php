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
  >
    <div class="flex shrink-0 flex-col gap-4 p-4 pb-3">
      <x-ui.page-header title="Inbox" />

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
          ])
        @else
          @include('inbox.partials.chat-empty')
        @endif
      </div>
    </div>
  </div>

  @include('inbox.partials.modals', ['activeModal' => $activeModal ?? null])
</x-layouts.app>
