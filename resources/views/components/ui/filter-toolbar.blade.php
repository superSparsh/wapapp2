@props(['searchPlaceholder' => 'Search'])

<div class="flex flex-col gap-4 p-4">
  <div class="flex flex-wrap items-center gap-2">
    {{ $before ?? '' }}
    <div class="flex min-w-[200px] flex-1 items-center gap-3 rounded-lg bg-elevated p-3 sm:max-w-[386px]">
      <x-icons.nav-icon name="search" class="size-5 shrink-0" />
      <input
        type="search"
        placeholder="{{ $searchPlaceholder }}"
        class="fd-filter-placeholder min-w-0 flex-1 bg-transparent focus:outline-none"
      >
    </div>
    {{ $filters ?? '' }}
    <div class="ml-auto flex flex-wrap items-center gap-2">
      {{ $actions ?? '' }}
    </div>
  </div>
  {{ $tabs ?? '' }}
</div>
