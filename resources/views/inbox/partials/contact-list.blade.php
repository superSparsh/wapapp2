@php
  $threads = $threads ?? [];
  $selectedConversation = $selectedConversation ?? null;
  $selectedUuid = $selectedConversation?->uuid;
  $filters = $filters ?? [];
  $query = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => $filters['scope'] ?? null,
    'assignee' => $filters['assignee'] ?? null,
    'days' => $filters['lookback_days'] ?? null,
  ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="flex min-h-0 w-full shrink-0 flex-col gap-4 lg:w-[405px]" data-inbox-thread-panel>
  <form
    method="get"
    action="{{ $selectedConversation ? route('inbox.show', $selectedConversation) : route('inbox.index') }}"
    class="flex shrink-0 items-center gap-3 rounded-lg bg-elevated p-3"
    data-inbox-search-form
  >
    @foreach (array_filter([
      'scope' => $filters['scope'] ?? null,
      'assignee' => $filters['assignee'] ?? null,
      'days' => $filters['lookback_days'] ?? null,
    ]) as $key => $value)
      <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <x-icons.nav-icon name="search-inbox" class="size-5 shrink-0 text-text-body/60" />
    <input
      type="search"
      name="q"
      value="{{ $filters['search'] ?? '' }}"
      placeholder="Search by name or number"
      class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none"
      data-inbox-search-input
      autocomplete="off"
    >
  </form>

  <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[12px] bg-elevated shadow-[0px_4px_6px_rgba(0,0,0,0.04)]">
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain" data-inbox-thread-list>
      @forelse ($threads as $thread)
        <a
          href="{{ route('inbox.show', ['conversation' => $thread['uuid']] + $query) }}"
          @class([
            'flex border-b border-divider px-2 py-1.5 last:border-0',
            'bg-green-50' => $thread['uuid'] === $selectedUuid,
            'bg-elevated hover:bg-surface' => $thread['uuid'] !== $selectedUuid,
          ])
          data-thread-uuid="{{ $thread['uuid'] }}"
        >
          <div class="flex min-w-0 flex-1 items-center gap-3 p-2">
            <div class="fd-btn-sm flex size-8 shrink-0 items-center justify-center rounded-2xl bg-green-50 text-green-500">{{ $thread['initials'] }}</div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center justify-between gap-2">
                <span class="fd-table-name truncate">{{ $thread['name'] }}</span>
                <span class="fd-status-chip shrink-0 text-text-body/60" data-thread-time>{{ $thread['time'] }}</span>
              </div>
              <div class="flex items-center justify-between gap-2">
                <p class="fd-table-cell truncate text-xs opacity-50" data-thread-preview>{{ $thread['preview'] }}</p>
                @if (! empty($thread['unread']))
                  <span class="fd-status-chip flex size-4 shrink-0 items-center justify-center rounded-full bg-green-500 text-white" data-thread-unread>{{ $thread['unread'] }}</span>
                @else
                  <span class="fd-status-chip hidden size-4 shrink-0 items-center justify-center rounded-full bg-green-500 text-white" data-thread-unread></span>
                @endif
              </div>
            </div>
          </div>
        </a>
      @empty
        <div class="p-6 text-center text-sm text-text-body/70" data-inbox-thread-empty>No conversations yet.</div>
      @endforelse
    </div>
  </div>
</div>
