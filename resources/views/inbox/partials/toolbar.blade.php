@php
  $filters = $filters ?? [];
  $filterOptions = $filterOptions ?? ['scopes' => [], 'lookback_days' => [], 'assignees' => []];
  $selectedConversation = $selectedConversation ?? null;
  $availableLines = $availableLines ?? [];
  $activeLine = $activeLine ?? null;
  $activeLineUuid = $activeLine->uuid ?? ($filters['line'] ?? null);
  $showLineFilter = count($availableLines) > 1;
  $showAddContact = (bool) ($showAddContact ?? true);

  $baseQuery = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => ($filters['scope'] ?? null) ?: null,
    'assignee' => ($filters['assignee'] ?? null) ?: null,
    'days' => $filters['lookback_days'] ?? null,
    'line' => $activeLineUuid,
  ], fn ($value) => $value !== null && $value !== '');

  $formAction = $selectedConversation
    ? route('inbox.show', $selectedConversation)
    : route('inbox.index');

  // Changing WhatsApp line must leave the open chat - that conversation belongs to another line.
  $lineFormAction = route('inbox.index');

  $currentScope = $filters['scope'] ?? 'all';
  $currentAssignee = $filters['assignee'] ?? 'all';
  $currentDays = (int) ($filters['lookback_days'] ?? config('inbox.default_lookback_days', 7));
  $lookbackLabels = config('inbox.lookback_labels', []);
  $lookbackLabel = $lookbackLabels[$currentDays] ?? ('Last '.$currentDays.' day'.($currentDays === 1 ? '' : 's'));
  $aiForAll = (bool) ($aiForAll ?? false);

  $scopeHidden = array_filter([
    'q' => $filters['search'] ?? null,
    'assignee' => $filters['assignee'] ?? null,
    'days' => $filters['lookback_days'] ?? null,
    'line' => $activeLineUuid,
  ], fn ($value) => $value !== null && $value !== '');

  $assigneeHidden = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => $filters['scope'] ?? null,
    'days' => $filters['lookback_days'] ?? null,
    'line' => $activeLineUuid,
  ], fn ($value) => $value !== null && $value !== '');

  $daysHidden = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => $filters['scope'] ?? null,
    'assignee' => $filters['assignee'] ?? null,
    'line' => $activeLineUuid,
  ], fn ($value) => $value !== null && $value !== '');

  $lineHidden = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => $filters['scope'] ?? null,
    'assignee' => $filters['assignee'] ?? null,
    'days' => $filters['lookback_days'] ?? null,
  ], fn ($value) => $value !== null && $value !== '');

  $activeFilterCount = 0;
  if ($showLineFilter && filled($activeLineUuid)) {
    $activeFilterCount++;
  }
  if (($currentScope ?? 'all') !== 'all' && filled($currentScope)) {
    $activeFilterCount++;
  }
  if (($currentAssignee ?? 'all') !== 'all' && filled($currentAssignee)) {
    $activeFilterCount++;
  }
  if ($currentDays !== (int) config('inbox.default_lookback_days', 7)) {
    $activeFilterCount++;
  }

  $filterSummary = $activeFilterCount > 0
    ? $activeFilterCount.' active'
    : 'All filters';
@endphp

<div class="flex w-full min-w-0 flex-wrap items-center gap-2">
  <div class="relative shrink-0" data-inbox-filter-dropdown>
    <button
      type="button"
      class="fd-btn inline-flex h-9 min-w-[148px] items-center justify-between gap-2 rounded-lg border border-border bg-elevated px-3 text-xs font-medium text-text-body transition hover:bg-surface"
      data-inbox-filter-toggle
      aria-expanded="false"
      aria-haspopup="true"
    >
      <span class="inline-flex items-center gap-1.5">
        <x-icons.nav-icon name="filter" class="size-3.5 shrink-0 opacity-70" />
        <span data-inbox-filter-label>Filters</span>
        @if ($activeFilterCount > 0)
          <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-green-500 px-1 text-[10px] font-semibold text-white">
            {{ $activeFilterCount }}
          </span>
        @endif
      </span>
      <x-icons.nav-icon name="arrow-down" class="size-3.5 shrink-0 opacity-60" />
    </button>

    <div
      class="absolute left-0 z-50 mt-1.5 hidden w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-border-light bg-elevated p-3 shadow-lg"
      data-inbox-filter-menu
      role="menu"
    >
      <div class="mb-2 flex items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-text-body/55">Filter conversations</p>
        <span class="text-[11px] text-text-body/45">{{ $filterSummary }}</span>
      </div>

      <div class="flex flex-col gap-2.5">
        @if ($showLineFilter)
          <div class="flex flex-col gap-1">
            <label class="text-[11px] font-medium text-text-body/60">WhatsApp number</label>
            @include('inbox.partials.filter-select', [
              'name' => 'line',
              'action' => $lineFormAction,
              'options' => collect($availableLines)->map(fn (array $line) => [
                'value' => $line['uuid'],
                'label' => $line['label'] ?: ($line['phone'] ?? $line['uuid']),
              ])->all(),
              'selected' => $activeLineUuid,
              'hidden' => $lineHidden,
              'formClass' => 'min-w-0 w-full',
              'minWidth' => 'min-w-0',
            ])
          </div>
        @endif

        <div class="flex flex-col gap-1">
          <label class="text-[11px] font-medium text-text-body/60">Scope</label>
          @include('inbox.partials.filter-select', [
            'name' => 'scope',
            'action' => $formAction,
            'options' => $filterOptions['scopes'],
            'selected' => $currentScope === null ? 'all' : $currentScope,
            'hidden' => $scopeHidden,
            'formClass' => 'min-w-0 w-full',
            'minWidth' => 'min-w-0',
          ])
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-[11px] font-medium text-text-body/60">Assignee</label>
          @include('inbox.partials.filter-select', [
            'name' => 'assignee',
            'action' => $formAction,
            'options' => $filterOptions['assignees'],
            'selected' => $currentAssignee === null ? 'all' : $currentAssignee,
            'hidden' => $assigneeHidden,
            'formClass' => 'min-w-0 w-full',
            'minWidth' => 'min-w-0',
          ])
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-[11px] font-medium text-text-body/60">Date range</label>
          @include('inbox.partials.filter-select', [
            'name' => 'days',
            'action' => $formAction,
            'options' => collect($filterOptions['lookback_days'])->map(fn (int $days) => [
              'value' => $days,
              'label' => $lookbackLabels[$days] ?? ('Last '.$days.' day'.((int) $days === 1 ? '' : 's')),
            ])->all(),
            'selected' => $currentDays,
            'hidden' => $daysHidden,
            'formClass' => 'min-w-0 w-full',
            'minWidth' => 'min-w-0',
            'title' => 'Chats and filters only include activity inside this date range',
          ])
          <p class="text-[11px] leading-snug text-text-body/45">
            Showing {{ $lookbackLabel }}. Longer range loads older chats.
          </p>
        </div>
      </div>
    </div>
  </div>

  <button
    type="button"
    data-inbox-mark-all-read
    data-mark-all-url="{{ route('inbox.api.mark-all-read') }}?{{ http_build_query($baseQuery) }}"
    class="fd-btn inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-green-500 bg-transparent px-2.5 text-xs font-medium text-green-600 transition hover:bg-green-50"
  >
    <x-icons.nav-icon name="refresh" class="size-3.5" />
    Mark all read
  </button>

  <button
    type="button"
    data-open-modal="export-by-date"
    class="fd-btn inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-border bg-elevated px-2.5 text-xs font-medium text-text-body transition hover:bg-surface"
  >
    <x-icons.nav-icon name="document-text" class="size-3.5" />
    Export by date
  </button>

  <div class="fd-btn inline-flex h-9 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-border bg-elevated px-2.5 text-xs font-medium text-text-body">
    AI for all
    <x-ui.toggle-switch
      :active="$aiForAll"
      data-inbox-ai-toggle-all
      aria-label="Enable AI for all conversations"
    />
  </div>

  <button
    type="button"
    data-inbox-notify-toggle
    class="fd-btn inline-flex h-9 shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-border bg-elevated px-2.5 text-xs font-medium text-text-body transition hover:bg-surface"
    aria-pressed="false"
    title="Browser notifications for new WhatsApp messages"
  >
    <x-icons.nav-icon name="bell" class="size-3.5" />
    <span data-inbox-notify-label>Notifications</span>
  </button>

  @if ($showAddContact)
    <button
      type="button"
      data-open-modal="add-contact"
      class="fd-btn ml-auto inline-flex h-9 shrink-0 items-center gap-2 whitespace-nowrap rounded-lg bg-green-500 px-3 text-sm font-medium text-primary-2 transition hover:opacity-90"
    >
      <x-icons.nav-icon name="add" class="size-5" />
      Add New Contact
    </button>
  @endif
</div>
