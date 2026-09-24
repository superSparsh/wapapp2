@php
  $threads = $threads ?? [];
  $selectedConversation = $selectedConversation ?? null;
  $selectedUuid = $selectedConversation?->uuid;
  $filters = $filters ?? [];
  $activeLine = $activeLine ?? null;
  $activeLineUuid = $activeLine->uuid ?? ($filters['line'] ?? null);
  $threadsCursor = $threadsCursor ?? null;
  $threadsHasMore = (bool) ($threadsHasMore ?? false);
  $query = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => $filters['scope'] ?? null,
    'assignee' => $filters['assignee'] ?? null,
    'days' => $filters['lookback_days'] ?? null,
    'line' => $activeLineUuid,
  ], fn ($value) => $value !== null && $value !== '');
  $lookbackDays = (int) ($filters['lookback_days'] ?? config('inbox.default_lookback_days', 7));
  $lookbackLabel = config('inbox.lookback_labels.'.$lookbackDays)
    ?? ('Last '.$lookbackDays.' day'.($lookbackDays === 1 ? '' : 's'));
@endphp

<div
  class="flex min-h-0 w-full shrink-0 flex-col gap-4 lg:w-[405px]"
  data-inbox-thread-panel
  data-threads-cursor="{{ $threadsCursor ?? '' }}"
  data-threads-has-more="{{ $threadsHasMore ? '1' : '0' }}"
>
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
      'line' => $activeLineUuid,
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
                <span class="fd-table-name truncate" data-thread-name>{{ $thread['name'] }}</span>
                <div class="flex shrink-0 items-center gap-1.5">
                  @if (! empty($thread['stopped']))
                    <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700" title="This contact marked STOP and is unsubscribed">STOP</span>
                  @endif
                  <span class="fd-status-chip text-text-body/60" data-thread-time>{{ $thread['time'] }}</span>
                </div>
              </div>
              @if (! empty($thread['phone']) && ($thread['phone'] !== $thread['name']))
                <p class="truncate text-[11px] leading-tight text-text-body/55" data-thread-phone>{{ $thread['phone'] }}</p>
              @else
                <p class="hidden truncate text-[11px] leading-tight text-text-body/55" data-thread-phone></p>
              @endif
              <div class="flex items-center justify-between gap-2">
                <p class="fd-table-cell truncate text-xs opacity-50" data-thread-preview>{{ $thread['preview'] }}</p>
                <div class="flex shrink-0 items-center gap-1.5">
                  @if (! empty($thread['assignee']))
                    <span class="hidden max-w-[72px] truncate rounded bg-surface px-1.5 py-0.5 text-[10px] font-medium text-text-body/70 sm:inline" title="Assigned: {{ $thread['assignee'] }}" data-thread-assignee>{{ $thread['assignee'] }}</span>
                  @endif
                  @if (! empty($thread['unread']))
                    <span class="fd-status-chip flex h-4 min-w-4 shrink-0 items-center justify-center rounded-full bg-green-500 px-1 text-white" data-thread-unread>{{ $thread['unread'] }}</span>
                  @else
                    <span class="fd-status-chip hidden h-4 min-w-4 shrink-0 items-center justify-center rounded-full bg-green-500 px-1 text-white" data-thread-unread></span>
                  @endif
                </div>
              </div>
            </div>
          </div>
        </a>
      @empty
        <div class="flex flex-col items-center gap-1 p-6 text-center" data-inbox-thread-empty>
          <p class="text-sm text-text-body/70">No conversations in {{ $lookbackLabel }}.</p>
          <p class="text-xs text-text-body/50">Try a longer date range in the filters above to load older chats.</p>
        </div>
      @endforelse
    </div>
  </div>
</div>
