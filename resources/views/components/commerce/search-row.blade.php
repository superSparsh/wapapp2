@props(['showRefresh' => true, 'refreshHref' => null])

@php
  $href = $refreshHref ?? request()->fullUrlWithQuery(['refresh' => 1]);
@endphp

<div class="flex flex-wrap items-center justify-end gap-3">
  <div class="flex w-full max-w-[550px] items-center gap-3 rounded-lg bg-elevated p-3">
    <img src="{{ asset('images/commerce/search.svg') }}" alt="" class="size-5 shrink-0" width="20" height="20">
    <input type="search" placeholder="Search" class="fd-filter-placeholder min-w-0 flex-1 bg-transparent text-sm font-medium leading-[1.4] text-text-body/60 focus:outline-none">
  </div>
  @if ($showRefresh)
    <a
      href="{{ $href }}"
      class="fd-btn inline-flex items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-sm font-semibold leading-[1.5] text-green-500 transition-colors hover:bg-surface"
    >
      <img src="{{ asset('images/commerce/refresh.svg') }}" alt="" class="size-4 shrink-0" width="16" height="16">
      Refresh
    </a>
  @endif
  {{ $slot }}
</div>
