@props(['variant' => 'secondary', 'size' => 'md'])

@php
  $classes = match ($variant) {
    'primary' => 'bg-green-500 text-primary-2 font-semibold border-transparent hover:opacity-90',
    'dashboard-action' => 'border border-border-light bg-elevated text-green-500 font-semibold hover:bg-surface',
    'green-outline' => 'border border-green-500 bg-green-50 text-green-500 font-semibold hover:bg-green-50/80',
    'outline' => 'border border-border-light bg-elevated text-primary-2 font-semibold hover:bg-surface',
    'toolbar' => 'border border-border-light bg-elevated text-primary-2 font-semibold hover:bg-surface',
    'chat-action' => 'border border-green-500 bg-green-100 text-primary-2 font-semibold hover:opacity-90',
    default => 'border border-border-light bg-elevated text-primary-2 font-semibold hover:bg-surface',
  };
  $sizeClass = match ($size) {
    'sm' => 'px-3 py-2.5 text-sm gap-2.5',
    'toolbar' => 'px-3 py-2.5 text-sm gap-2.5',
    default => 'px-4 py-3 text-sm gap-3',
  };
  $radius = match ($size) {
    'toolbar' => 'rounded',
    default => 'rounded-lg',
  };
@endphp

<a {{ $attributes->merge(['class' => "fd-btn inline-flex items-center justify-center {$radius} {$sizeClass} transition-colors {$classes}"]) }}>
  {{ $slot }}
</a>
