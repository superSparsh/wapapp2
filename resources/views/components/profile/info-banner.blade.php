@props(['title' => null, 'variant' => 'info'])

@php
  $styles = [
    'info' => 'bg-stat-blue/15',
    'warning' => 'bg-stat-orange/15',
  ];
@endphp

<div {{ $attributes->merge(['class' => 'flex gap-3 rounded-xl p-3.5 ' . ($styles[$variant] ?? $styles['info'])]) }}>
  <img src="{{ asset('images/profile/info-circle.svg') }}" alt="" class="size-6 shrink-0" width="24" height="24">
  <div class="min-w-0 flex-1 space-y-2.5 text-sm leading-[1.4] text-text-body">
    @if ($title)
      <p class="fd-label">{{ $title }}</p>
    @endif
    <div class="fd-page-note !opacity-100 text-text-muted">{{ $slot }}</div>
  </div>
</div>
