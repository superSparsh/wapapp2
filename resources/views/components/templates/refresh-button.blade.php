@props(['action' => null])

@php
  $refreshUrl = $action ?? url()->current();
  $separator = str_contains($refreshUrl, '?') ? '&' : '?';
  if (! str_contains($refreshUrl, 'refresh=')) {
      $refreshUrl .= $separator.'refresh=1';
  }
@endphp

<a
  href="{{ $refreshUrl }}"
  class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-green-500"
>
  <img src="{{ asset('images/templates/refresh-2.svg') }}" alt="" class="size-4" width="16" height="16">
  Refresh
</a>
