@props(['action' => null])

@php
  $refreshUrl = $action ?? url()->current();
@endphp

<form method="get" action="{{ $refreshUrl }}" class="inline">
  @foreach (request()->except('refresh', 'page') as $key => $value)
    @if (is_array($value))
      @foreach ($value as $item)
        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
      @endforeach
    @else
      <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endif
  @endforeach
  <input type="hidden" name="refresh" value="1">
  <button type="submit" class="fd-btn inline-flex shrink-0 items-center justify-center gap-3 rounded border border-border-light bg-elevated px-4 py-3 text-green-500">
    <img src="{{ asset('images/templates/refresh-2.svg') }}" alt="" class="size-4" width="16" height="16">
    Refresh
  </button>
</form>
