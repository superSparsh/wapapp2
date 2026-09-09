@props([
    'action',
    'search' => '',
    'searchName' => 'q',
    'searchPlaceholder' => 'Search…',
    'dateFrom' => '',
    'dateTo' => '',
    'showDates' => true,
    'sort' => '',
    'direction' => 'desc',
    'sortOptions' => [],
    'showSort' => true,
])

@php
  $sortComposite = $sort !== '' ? ($sort.':'.$direction) : '';
@endphp

<form
  method="GET"
  action="{{ $action }}"
  class="mx-4 mb-4 flex flex-wrap items-end gap-3 rounded-[20px] border border-border bg-elevated p-4"
  data-listing-toolbar
  {{ $attributes }}
>
  {{ $hidden ?? '' }}

  @if ($showSort && count($sortOptions) > 0)
    <input type="hidden" name="sort" value="{{ $sort }}" data-listing-sort-field>
    <input type="hidden" name="direction" value="{{ $direction }}" data-listing-sort-direction>

    <label class="flex min-w-[160px] flex-col gap-1.5 text-sm">
      <span class="font-semibold text-text-primary">Sort by</span>
      <select
        data-listing-sort
        data-listing-sort-current="{{ $sortComposite }}"
        class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
        aria-label="Sort by"
      >
        @foreach ($sortOptions as $option)
          @php
            $value = is_array($option) ? ($option['value'] ?? '') : $option;
            $label = is_array($option) ? ($option['label'] ?? $value) : $option;
            $dir = is_array($option) ? ($option['direction'] ?? 'desc') : 'desc';
            $composite = $value.':'.$dir;
          @endphp
          <option value="{{ $composite }}" @selected($sortComposite === $composite)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
  @endif

  {{ $filters ?? '' }}

  @if ($showDates)
    <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
      <span class="font-semibold text-text-primary">From (IST)</span>
      <input
        type="date"
        name="date_from"
        value="{{ $dateFrom }}"
        class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
      >
    </label>
    <label class="flex min-w-[150px] flex-col gap-1.5 text-sm">
      <span class="font-semibold text-text-primary">To (IST)</span>
      <input
        type="date"
        name="date_to"
        value="{{ $dateTo }}"
        class="rounded-lg border border-border bg-surface px-3 py-2 text-sm text-text-primary"
      >
    </label>
  @endif

  <label class="flex min-w-[220px] flex-1 flex-col gap-1.5 text-sm">
    <span class="font-semibold text-text-primary">Search</span>
    <div class="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-2">
      <img src="{{ asset('images/automation/search.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
      <input
        type="search"
        name="{{ $searchName }}"
        value="{{ $search }}"
        placeholder="{{ $searchPlaceholder }}"
        data-listing-search-enter
        autocomplete="off"
        class="min-w-0 flex-1 bg-transparent text-sm text-text-primary placeholder:text-text-subtle focus:outline-none"
      >
    </div>
  </label>

  <button type="submit" class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">
    Apply
  </button>

  @if (isset($actions))
    <div class="flex shrink-0 items-center gap-2">
      {{ $actions }}
    </div>
  @endif
</form>
