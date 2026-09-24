@php
  $filters = $filters ?? [];
  $filterOptions = $filterOptions ?? ['scopes' => [], 'lookback_days' => [], 'assignees' => []];
  $selectedConversation = $selectedConversation ?? null;
  $availableLines = $availableLines ?? [];
  $activeLine = $activeLine ?? null;
  $activeLineUuid = $activeLine->uuid ?? ($filters['line'] ?? null);
  $showLineFilter = count($availableLines) > 1;

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

  // Changing WhatsApp line must leave the open chat — that conversation belongs to another line.
  $lineFormAction = route('inbox.index');

  $currentScope = $filters['scope'] ?? 'all';
  $currentAssignee = $filters['assignee'] ?? 'all';
  $currentDays = (int) ($filters['lookback_days'] ?? config('inbox.default_lookback_days', 7));
  $lookbackLabels = config('inbox.lookback_labels', []);
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
@endphp

<div class="flex w-full flex-col gap-2">
  <div @class([
    'grid min-w-0 grid-cols-1 gap-2',
    'sm:grid-cols-4' => $showLineFilter,
    'sm:grid-cols-3' => ! $showLineFilter,
  ])>
    @if ($showLineFilter)
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
      ])
    @endif

    @include('inbox.partials.filter-select', [
      'name' => 'scope',
      'action' => $formAction,
      'options' => $filterOptions['scopes'],
      'selected' => $currentScope === null ? 'all' : $currentScope,
      'hidden' => $scopeHidden,
      'formClass' => 'min-w-0 w-full',
    ])

    @include('inbox.partials.filter-select', [
      'name' => 'assignee',
      'action' => $formAction,
      'options' => $filterOptions['assignees'],
      'selected' => $currentAssignee === null ? 'all' : $currentAssignee,
      'hidden' => $assigneeHidden,
      'formClass' => 'min-w-0 w-full',
    ])

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
    ])
  </div>

  <div class="flex flex-wrap items-center gap-2">
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
  </div>
</div>
