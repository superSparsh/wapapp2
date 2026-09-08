@props([
    'name' => null,
    'id' => null,
    'variant' => 'default',
    'disabled' => false,
    'required' => false,
])

@php
  $variants = [
    'default' => 'w-full rounded-xl border border-border bg-elevated px-3.5 py-3.5 text-sm font-medium leading-[1.4] text-text-primary focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500',
    'filter' => 'fd-filter-label w-full rounded-lg bg-elevated py-3 pr-9 pl-3 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500',
    'listing' => 'w-full rounded-lg border border-border bg-elevated py-2 pl-2.5 pr-8 text-xs font-medium leading-[1.4] text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500',
    'compact' => 'w-full rounded-lg border border-border bg-elevated px-3 py-2 text-xs font-medium text-text-body focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500',
    'header' => 'max-w-[160px] rounded bg-green-600 px-2 py-1 text-xs text-white focus:outline-none',
  ];

  $selectClass = $variants[$variant] ?? $variants['default'];
@endphp

<select
  @if ($name) name="{{ $name }}" @endif
  @if ($id) id="{{ $id }}" @endif
  @disabled($disabled)
  @required($required)
  data-select-variant="{{ $variant }}"
  {{ $attributes->merge(['class' => $selectClass]) }}
>
  {{ $slot }}
</select>
