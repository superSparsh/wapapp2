@props(['label', 'variant' => 'blue'])

@php
  $styles = match ($variant) {
    'green' => 'bg-[rgba(0,128,0,0.1)] text-[10px] text-green-600',
    'orange' => 'bg-[rgba(255,165,0,0.1)] text-[10px] text-orange-500',
    default => 'bg-[rgba(59,130,246,0.1)] text-[13px] text-stat-blue',
  };
@endphp

<span class="inline-flex items-center justify-center rounded px-2 py-1 font-medium leading-[1.2] {{ $styles }}">
  {{ $label }}
</span>
