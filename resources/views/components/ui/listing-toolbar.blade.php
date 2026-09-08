@props([
    'action',
    'method' => 'GET',
    'searchName' => 'search',
    'searchValue' => '',
    'searchPlaceholder' => 'Search',
    'showSort' => true,
    'sortName' => 'sort',
    'directionName' => 'direction',
    'currentSort' => 'created_at',
    'currentDirection' => 'desc',
    'sortOptions' => [],
    'searchOnEnter' => true,
])

@php
  $sortComposite = $currentSort . ':' . $currentDirection;
  $hasMatchingSort = collect($sortOptions)->contains(function ($option) use ($currentSort, $currentDirection) {
      $value = is_array($option) ? ($option['value'] ?? '') : $option;
      $direction = is_array($option)
          ? ($option['direction'] ?? ($value === 'name' ? 'asc' : 'desc'))
          : ($value === 'name' ? 'asc' : 'desc');

      return (string) $value === (string) $currentSort
          && (string) $direction === (string) $currentDirection;
  });

  if (! $hasMatchingSort && count($sortOptions) > 0) {
      $first = $sortOptions[0];
      $firstValue = is_array($first) ? ($first['value'] ?? 'created_at') : $first;
      $firstDirection = is_array($first)
          ? ($first['direction'] ?? ($firstValue === 'name' ? 'asc' : 'desc'))
          : ($firstValue === 'name' ? 'asc' : 'desc');
      $sortComposite = $firstValue . ':' . $firstDirection;
  }
@endphp

<form
  method="{{ $method }}"
  action="{{ $action }}"
  class="flex w-full items-center justify-between gap-3"
  data-listing-toolbar
  {{ $attributes }}
>
  {{ $hidden ?? '' }}

  <div class="flex min-w-0 flex-1 items-center gap-2 overflow-visible" data-listing-controls>
    @if ($showSort && count($sortOptions) > 0)
      <input type="hidden" name="{{ $sortName }}" value="{{ $currentSort }}" data-listing-sort-field>
      <input type="hidden" name="{{ $directionName }}" value="{{ $currentDirection }}" data-listing-sort-direction>

      <div class="flex shrink-0 items-center gap-2">
        <div class="size-4 shrink-0 border-[1.5px] border-solid border-border-light bg-elevated"></div>
        <span class="text-sm font-semibold leading-[1.4] whitespace-nowrap text-primary-2">Sort by</span>
      </div>

      <x-ui.select
        variant="listing"
        data-listing-sort
        data-listing-sort-current="{{ $sortComposite }}"
        class="w-[148px] shrink-0"
        aria-label="Sort by"
      >
        @foreach ($sortOptions as $option)
          @php
            $value = is_array($option) ? ($option['value'] ?? '') : $option;
            $label = is_array($option) ? ($option['label'] ?? $value) : $option;
            $direction = is_array($option)
                ? ($option['direction'] ?? ($value === 'name' ? 'asc' : 'desc'))
                : ($value === 'name' ? 'asc' : 'desc');
            $composite = $value . ':' . $direction;
          @endphp
          <option value="{{ $composite }}" @selected($sortComposite === $composite)>{{ $label }}</option>
        @endforeach
      </x-ui.select>
    @endif

    {{ $filters ?? '' }}

    <div class="flex w-[220px] shrink-0 items-center gap-2 overflow-hidden rounded-lg bg-elevated px-2.5 py-2">
      <img src="{{ asset('images/automation/search.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
      <input
        type="search"
        name="{{ $searchName }}"
        value="{{ $searchValue }}"
        placeholder="{{ $searchPlaceholder }}"
        @if ($searchOnEnter) data-listing-search-enter @endif
        autocomplete="off"
        class="min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body placeholder:text-text-body/60 focus:outline-none"
      >
    </div>
  </div>

  @if (isset($actions))
    <div class="flex shrink-0 items-center gap-3">
      {{ $actions }}
    </div>
  @endif
</form>
