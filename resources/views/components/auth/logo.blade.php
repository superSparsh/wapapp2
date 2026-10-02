@props([
    'size' => 'default',
])

@php
    $isCompact = $size === 'compact';
@endphp

<x-brand.logo
    variant="auth"
    {{ $attributes->class($isCompact ? 'h-20 w-20 rounded-2xl' : 'rounded-2xl') }}
    @if (! $isCompact)
        style="width: 250px; height: 250px; left: -13%;"
    @endif
/>
