@props(['name', 'class' => 'size-5', 'active' => false])

@php
    $iconMap = [
        'smart-home' => 'smart-home',
        'users' => 'users',
        'box' => 'box',
        'ticket' => 'ticket',
        'file-text' => 'file-text',
        'star' => 'star',
        'shopping-cart' => 'shopping-cart',
        'circle-square' => 'box',
    ];

    $base = $iconMap[$name] ?? null;

    if ($base === 'shopping-cart') {
        $file = 'shopping-cart.svg';
    } elseif ($base) {
        $file = $base . ($active ? '-active' : '-inactive') . '.svg';
    } else {
        $file = null;
    }
@endphp

@if ($file)
  <img
    {{ $attributes->merge(['class' => "inline-block shrink-0 object-contain {$class}"]) }}
    src="{{ asset('images/icons/sidebar/menu/' . $file) }}"
    alt=""
    width="20"
    height="20"
  >
@else
  <span {{ $attributes->merge(['class' => "inline-block shrink-0 rounded bg-border-light {$class}"]) }}></span>
@endif
