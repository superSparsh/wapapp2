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
        'bell' => 'bell',
    ];

    $base = $iconMap[$name] ?? null;
    $src = null;
    $stroke = $active ? '#FFFFFF' : '#9EA5B5';

    if ($base === 'shopping-cart') {
        $src = asset('images/icons/sidebar/menu/shopping-cart.svg');
    } elseif ($base && $base !== 'bell') {
        $src = asset('images/icons/sidebar/menu/'.$base.($active ? '-active' : '-inactive').'.svg');
    }
@endphp

@if ($base === 'bell')
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 20 20"
    fill="none"
    {{ $attributes->merge(['class' => "inline-block shrink-0 {$class}"]) }}
    width="20"
    height="20"
    aria-hidden="true"
  >
    <path
      d="M5 8.2C5 5.6 7.01 3.5 9.5 3.5H10.5C12.99 3.5 15 5.6 15 8.2V11.5L16.5 13.8H3.5L5 11.5V8.2Z"
      stroke="{{ $stroke }}"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <path
      d="M8.25 16C8.7 16.85 9.3 17.35 10 17.35C10.7 17.35 11.3 16.85 11.75 16"
      stroke="{{ $stroke }}"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
@elseif ($src)
  <img
    {{ $attributes->merge(['class' => "inline-block shrink-0 object-contain {$class}"]) }}
    src="{{ $src }}"
    alt=""
    width="20"
    height="20"
  >
@else
  <span {{ $attributes->merge(['class' => "inline-block shrink-0 rounded bg-border-light {$class}"]) }}></span>
@endif
