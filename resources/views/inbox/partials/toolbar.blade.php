@php
  $filters = $filters ?? [];
  $filterOptions = $filterOptions ?? ['scopes' => [], 'lookback_days' => [], 'assignees' => []];
  $selectedConversation = $selectedConversation ?? null;

  $baseQuery = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => ($filters['scope'] ?? null) ?: null,
    'assignee' => ($filters['assignee'] ?? null) ?: null,
    'days' => $filters['lookback_days'] ?? null,
  ], fn ($value) => $value !== null && $value !== '');

  $formAction = $selectedConversation
    ? route('inbox.show', $selectedConversation)
    : route('inbox.index');

  $currentScope = $filters['scope'] ?? 'all';
  $currentAssignee = $filters['assignee'] ?? 'all';
  $currentDays = (int) ($filters['lookback_days'] ?? config('inbox.default_lookback_days', 7));

  $scopeHidden = array_filter([
    'q' => $filters['search'] ?? null,
    'assignee' => $filters['assignee'] ?? null,
    'days' => $filters['lookback_days'] ?? null,
  ], fn ($value) => $value !== null && $value !== '');

  $assigneeHidden = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => $filters['scope'] ?? null,
    'days' => $filters['lookback_days'] ?? null,
  ], fn ($value) => $value !== null && $value !== '');

  $daysHidden = array_filter([
    'q' => $filters['search'] ?? null,
    'scope' => $filters['scope'] ?? null,
    'assignee' => $filters['assignee'] ?? null,
  ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="flex w-full flex-nowrap items-center gap-2 lg:gap-3">
  <div class="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-3">
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
        'label' => 'Last '.$days.' day'.((int) $days === 1 ? '' : 's'),
      ])->all(),
      'selected' => $currentDays,
      'hidden' => $daysHidden,
      'formClass' => 'min-w-0 w-full',
    ])
  </div>

  <div class="flex shrink-0 items-center gap-2 lg:gap-3">
    <button
      type="button"
      data-inbox-mark-all-read
      data-mark-all-url="{{ route('inbox.api.mark-all-read') }}?{{ http_build_query($baseQuery) }}"
      class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-green-500 bg-transparent px-3 py-3 text-sm font-medium text-green-600 transition hover:bg-green-50 lg:px-4"
    >
      <x-icons.nav-icon name="refresh" class="size-4" />
      <span class="hidden sm:inline">Mark All Read</span>
      <span class="sm:hidden">Read All</span>
    </button>

    <a
      href="{{ route('inbox.api.export-all', $baseQuery) }}"
      data-inbox-export-all
      class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-border bg-elevated px-3 py-3 text-sm font-medium text-text-body transition hover:bg-surface lg:px-4"
    >
      <x-icons.nav-icon name="document-text" class="size-4" />
      <span class="hidden sm:inline">Export All</span>
      <span class="sm:hidden">Export</span>
    </a>

    <div class="fd-btn inline-flex shrink-0 items-center justify-center gap-2.5 whitespace-nowrap rounded-lg border border-border bg-elevated px-3 py-3 text-sm font-medium text-text-body lg:px-4">
      <span class="hidden sm:inline">AI for All</span>
      <span class="sm:hidden">AI</span>
      <x-ui.toggle-switch
        :active="false"
        data-inbox-ai-toggle-all
        aria-label="Enable AI for all conversations"
      />
    </div>

    <button
      type="button"
      data-open-modal="add-contact"
      class="fd-btn inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-green-500 px-3 py-3 text-sm font-medium text-primary-2 transition hover:opacity-90 lg:px-4"
    >
      <x-icons.nav-icon name="add" class="size-5" />
      <span class="hidden sm:inline">Add New Contact</span>
      <span class="sm:hidden">Add</span>
    </button>
  </div>
</div>
