@props(['variant' => 'default'])

@php
  $classes = match ($variant) {
    'success' => 'bg-green-50 text-green-600 border-green-200',
    'warning' => 'bg-amber-50 text-amber-600 border-amber-200',
    'danger' => 'bg-red-50 text-red-600 border-red-200',
    default => 'bg-surface text-text-muted border-border',
  };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {$classes}"]) }}>
  {{ $slot }}
</span>
